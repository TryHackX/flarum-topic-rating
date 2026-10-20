<?php

namespace TryHackX\TopicRating\Api;

use Flarum\Api\Context;
use Flarum\Api\Schema;
use Flarum\Discussion\Discussion;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use TryHackX\TopicRating\Access\RatingPolicy;
use TryHackX\TopicRating\AuthorMode;
use TryHackX\TopicRating\AuthorModeResolver;
use TryHackX\TopicRating\Rating;

/**
 * Rating-related fields added to the DiscussionResource. Extracted from
 * extend.php so the policy and settings come in through constructor injection
 * (resolved once per request by the container) instead of a resolve() call
 * inside every per-model closure. As a bonus the single injected RatingPolicy
 * shares its per-request memoization across every discussion in the response.
 *
 * Registered via `->fields(DiscussionRatingFields::class)`.
 */
class DiscussionRatingFields
{
    /**
     * The actor's own ratings, loaded in a single query the first time
     * `userRating` is read — which turns the old one-query-per-discussion lookup
     * (an N+1 on every discussion-list render) into one query per request.
     *
     * Keyed by the actor INSTANCE, not the user id: these field definitions are
     * built once per API resource and reused by every request the process serves
     * (several in a row in the test suite, or in a long-running server — even a
     * forum page's own preload goes through the API). Each request loads its own
     * actor, so a rating written by an earlier request is never read from a stale
     * preload (an id-keyed cache answered `null` right after the user rated).
     * Per actor: `ratings` [discussionId => rating], `complete` (the preload
     * wasn't truncated by the cap, so a missing id means "not rated") and
     * `misses` (per-discussion fallback lookups, nulls included).
     *
     * @var \WeakMap<User, object{ratings: array<int, int>, complete: bool, misses: array<int, int|null>}>
     */
    protected \WeakMap $actorRatings;

    /**
     * Upper bound on the per-request bulk preload of an actor's ratings. Chosen
     * far above any realistic per-user rating count, so normal users load in one
     * query with no fallbacks; only pathological voters hit the bounded path.
     */
    protected const PRELOAD_CAP = 5000;

    public function __construct(
        protected RatingPolicy $policy,
        protected SettingsRepositoryInterface $settings,
        protected AuthorModeResolver $authorModes
    ) {
        $this->actorRatings = new \WeakMap();
    }

    public function __invoke(): array
    {
        return [
            // The effective score for the discussion's author mode (maintained by
            // RatingRecalculator) — the same columns other extensions sort on.
            Schema\Integer::make('ratingCount')
                ->get(fn (Discussion $discussion) => (int) $discussion->rating_count),
            Schema\Number::make('ratingAverage')
                ->get(fn (Discussion $discussion) => (float) $discussion->rating_average),
            Schema\DateTime::make('lastRatedAt')
                ->nullable()
                ->get(fn (Discussion $discussion) => $discussion->last_rated_at),
            Schema\Boolean::make('ratingDisabled')
                ->get(fn (Discussion $discussion) => (bool) $discussion->rating_disabled),
            Schema\Boolean::make('canRate')
                ->get(fn (Discussion $discussion, Context $context) =>
                    $context->getActor()->can('rate', $discussion)
                ),
            Schema\Boolean::make('canRateRequiresActivation')
                ->get(function (Discussion $discussion, Context $context) {
                    $actor = $context->getActor();
                    if (! $actor->id || $actor->is_email_confirmed
                        || $actor->can('rate', $discussion)
                        || $discussion->rating_disabled
                        || ! $this->policy->ratingEnabled()
                        // Activating wouldn't help when the author mode rules them out.
                        || $this->policy->authorRuleBlocks($actor, $discussion)
                    ) {
                        return false;
                    }
                    if ($this->policy->actorBypassesGlobal($actor)) {
                        return false;
                    }
                    return ! (bool) $this->settings->get('tryhackx-topic-rating.allow_unactivated', false);
                }),
            Schema\Str::make('ratingDisplayMode')
                ->get(function (Discussion $discussion, Context $context) {
                    $actor = $context->getActor();

                    // Master switch off: no widget anywhere, for anyone.
                    if (! $this->policy->ratingEnabled()) {
                        return 'hidden';
                    }

                    // Tagless discussions with "allow rating on untagged
                    // discussions" turned off are hidden entirely (regardless of
                    // the restricted-display mode, and for everyone).
                    if ($this->policy->ratingForcedHidden($discussion)) {
                        return 'hidden';
                    }

                    // Those who can rate always see the widget (incl. bypassers).
                    if ($actor->can('rate', $discussion)) {
                        return 'rate';
                    }

                    // Hide existing ratings on topics whose rating is fully
                    // disabled (all tags disabled, or moderator-disabled) for
                    // viewers who can't rate — prevents "rate it, then switch to a
                    // disabled tag to lock the score" abuse. On by default; when
                    // off such topics fall back to the restricted-display mode.
                    if ((bool) $this->settings->get('tryhackx-topic-rating.hide_disabled_ratings', true)
                        && $this->policy->isRatingDisabled($discussion)) {
                        return 'hidden';
                    }

                    // Ruled out only by the author mode (the author under
                    // "exclude", everyone else under "author only"): the score is
                    // still meant to be seen — it's the review's score — so show
                    // it read-only rather than as a group restriction.
                    if ($this->policy->authorRuleBlocks($actor, $discussion)) {
                        return 'readonly';
                    }

                    $mode = (string) $this->settings->get('tryhackx-topic-rating.display_when_restricted', 'readonly');
                    if (! in_array($mode, ['readonly', 'hidden', 'message'], true)) {
                        $mode = 'readonly';
                    }
                    return $mode;
                }),
            Schema\Boolean::make('canToggleRating')
                ->get(fn (Discussion $discussion, Context $context) =>
                    $context->getActor()->hasPermission('discussion.rate.toggle')
                ),
            Schema\Boolean::make('canResetRatings')
                ->get(fn (Discussion $discussion, Context $context) =>
                    $context->getActor()->hasPermission('discussion.rate.reset')
                ),
            // Every stored rating, whichever of them the author mode counts — for
            // the moderators' "Reset All Ratings" (which deletes them all).
            Schema\Integer::make('ratingTotalCount')
                ->visible(fn (Discussion $discussion, Context $context) =>
                    $context->getActor()->hasPermission('discussion.rate.reset')
                )
                ->get(fn (Discussion $discussion) =>
                    (int) $discussion->community_rating_count + ($discussion->author_rating !== null ? 1 : 0)
                ),
            // Author mode ("review mode") that applies to this discussion — see
            // AuthorMode. Drives the author badge / labels on the frontend.
            Schema\Str::make('ratingAuthorMode')
                ->get(fn (Discussion $discussion) => $this->authorModes->resolve($discussion)),
            // The author's own rating (1–10) — only in the modes that show it as a
            // value of its own; null otherwise (and when the author hasn't rated).
            Schema\Integer::make('authorRating')
                ->nullable()
                ->get(function (Discussion $discussion) {
                    if (! AuthorMode::exposesAuthorRating($this->authorModes->resolve($discussion))) {
                        return null;
                    }

                    return $discussion->author_rating === null ? null : (int) $discussion->author_rating;
                }),
            Schema\Boolean::make('ratingViewerIsAuthor')
                ->get(fn (Discussion $discussion, Context $context) =>
                    $this->policy->isAuthor($context->getActor(), $discussion)
                ),
            Schema\Integer::make('userRating')
                ->nullable()
                ->get(function (Discussion $discussion, Context $context) {
                    $actor = $context->getActor();
                    if (! $actor->id) {
                        return null;
                    }
                    return $this->userRatingFor($actor, (int) $discussion->id);
                }),
        ];
    }

    /**
     * The acting user's rating for one discussion (0–10), or null if unrated.
     *
     * Backed by a single bulk load of the actor's ratings on first use, capped at
     * {@see self::PRELOAD_CAP} rows so a pathological voter (tens of thousands of
     * ratings) can't hydrate an unbounded result set on every discussion-list
     * render. When the cap is hit, discussions outside the preloaded (most-recent)
     * set fall back to a single cached per-discussion lookup, so the value stays
     * correct without re-running the N+1 the bulk load exists to prevent. The
     * Flarum 2.x field-serializer context exposes no list of the page's models
     * (search results are null in getters), so this cap is the bound available
     * without re-architecting the resource layer.
     */
    protected function userRatingFor(User $actor, int $discussionId): ?int
    {
        $cache = $this->actorRatingsFor($actor);

        if (array_key_exists($discussionId, $cache->ratings)) {
            return $cache->ratings[$discussionId];
        }

        // The preload held the actor's full set — an absent id genuinely means
        // "not rated", no lookup needed.
        if ($cache->complete) {
            return null;
        }

        // Capped preload may have omitted this discussion; resolve it directly and
        // cache the result (incl. nulls) so repeats within the request are free.
        if (! array_key_exists($discussionId, $cache->misses)) {
            $rating = Rating::query()
                ->where('user_id', $actor->id)
                ->where('discussion_id', $discussionId)
                ->value('rating');
            $cache->misses[$discussionId] = $rating === null ? null : (int) $rating;
        }

        return $cache->misses[$discussionId];
    }

    /**
     * The actor's ratings ([discussionId => rating], capped at the most-recent
     * {@see self::PRELOAD_CAP} entries), loaded once per actor instance.
     *
     * @return object{ratings: array<int, int>, complete: bool, misses: array<int, int|null>}
     */
    protected function actorRatingsFor(User $actor): object
    {
        if (! isset($this->actorRatings[$actor])) {
            // Fetch one more than the cap so we can tell whether the set was
            // truncated (and thus whether per-discussion fallbacks are needed).
            $rows = Rating::query()
                ->where('user_id', $actor->id)
                ->orderByDesc('id')
                ->limit(self::PRELOAD_CAP + 1)
                ->pluck('rating', 'discussion_id');

            $this->actorRatings[$actor] = (object) [
                'ratings' => $rows->take(self::PRELOAD_CAP)->map(fn ($r) => (int) $r)->all(),
                'complete' => $rows->count() <= self::PRELOAD_CAP,
                'misses' => [],
            ];
        }

        return $this->actorRatings[$actor];
    }
}
