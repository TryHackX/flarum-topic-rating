<?php

namespace TryHackX\TopicRating\Api;

use Flarum\Api\Context;
use Flarum\Api\Schema;
use Flarum\Discussion\Discussion;
use Flarum\Settings\SettingsRepositoryInterface;
use TryHackX\TopicRating\Access\RatingPolicy;
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
     * Per-request cache of the actor's own ratings, keyed by actor id then
     * discussion id. Loaded in a single query the first time `userRating` is
     * read, which turns the old one-query-per-discussion lookup (an N+1 on
     * every discussion-list render) into one query per request.
     *
     * @var array<int, array<int, int>>
     */
    protected array $actorRatings = [];

    public function __construct(
        protected RatingPolicy $policy,
        protected SettingsRepositoryInterface $settings
    ) {
    }

    public function __invoke(): array
    {
        return [
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
            Schema\Integer::make('userRating')
                ->nullable()
                ->get(function (Discussion $discussion, Context $context) {
                    $actor = $context->getActor();
                    if (! $actor->id) {
                        return null;
                    }
                    $ratings = $this->actorRatingsFor((int) $actor->id);
                    return $ratings[(int) $discussion->id] ?? null;
                }),
        ];
    }

    /**
     * The actor's ratings as a [discussionId => rating] map, loaded once.
     *
     * @return array<int, int>
     */
    protected function actorRatingsFor(int $actorId): array
    {
        if (! isset($this->actorRatings[$actorId])) {
            $this->actorRatings[$actorId] = Rating::query()
                ->where('user_id', $actorId)
                ->pluck('rating', 'discussion_id')
                ->map(fn ($r) => (int) $r)
                ->all();
        }

        return $this->actorRatings[$actorId];
    }
}
