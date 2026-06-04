<?php

use TryHackX\TopicRating\Api\Controller;
use TryHackX\TopicRating\Api\Resource\RatingResource;
use TryHackX\TopicRating\Access\RatingPolicy;
use TryHackX\TopicRating\Listener\CleanupTagConfig;
use TryHackX\TopicRating\Rating;
use Flarum\Api\Resource\DiscussionResource;
use Flarum\Api\Context;
use Flarum\Api\Schema;
use Flarum\Discussion\Discussion;
use Flarum\Extend;

// Shared avatar-display mode, coordinated with flarum-thumb-sliders. Both
// extensions serialize the same neutral `tryhackx-avatars.*` keys so the value
// is shared whether one or both are installed. Defaults are baked in here
// (NOT ->default(), which throws when both extensions register the same key);
// the duplicate serializer is safe because ForumResource fields are keyed by
// attribute name (last-wins) and both extensions yield the identical value.
$normalizeAvatarMode = function ($value) {
    $v = is_string($value) ? $value : '';

    return in_array($v, ['show', 'with_image', 'always', 'hide'], true) ? $v : 'show';
};

$normalizeListStyle = function ($value) {
    $v = is_string($value) ? $value : '';
    $allowed = ['full_meta', 'full_title', 'single_filled_meta', 'single_filled_title', 'single_bucket_meta', 'single_bucket_title'];

    return in_array($v, $allowed, true) ? $v : 'full_meta';
};

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js')
        ->css(__DIR__ . '/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js')
        ->css(__DIR__ . '/less/admin.less'),

    new Extend\Locales(__DIR__ . '/locale'),

    (new Extend\Model(Discussion::class))
        ->hasMany('ratings', Rating::class, 'discussion_id')
        ->cast('last_rated_at', 'datetime'),

    // Register RatingResource (handles listing via Index endpoint)
    (new Extend\ApiResource(RatingResource::class)),

    // Extend DiscussionResource with rating fields
    (new Extend\ApiResource(DiscussionResource::class))
        ->fields(fn () => [
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
                    $policy = resolve(\TryHackX\TopicRating\Access\RatingPolicy::class);
                    if ($policy->actorBypassesGlobal($actor)) {
                        return false;
                    }
                    $settings = resolve(\Flarum\Settings\SettingsRepositoryInterface::class);
                    return ! (bool) $settings->get('tryhackx-topic-rating.allow_unactivated', false);
                }),
            Schema\Str::make('ratingDisplayMode')
                ->get(function (Discussion $discussion, Context $context) {
                    $actor = $context->getActor();
                    $policy = resolve(\TryHackX\TopicRating\Access\RatingPolicy::class);

                    // Tagless discussions with "allow rating on untagged
                    // discussions" turned off are hidden entirely (regardless of
                    // the restricted-display mode, and for everyone).
                    if ($policy->ratingForcedHidden($discussion)) {
                        return 'hidden';
                    }

                    // Those who can rate always see the widget (incl. bypassers).
                    if ($actor->can('rate', $discussion)) {
                        return 'rate';
                    }

                    $settings = resolve(\Flarum\Settings\SettingsRepositoryInterface::class);

                    // Hide existing ratings on topics whose rating is fully
                    // disabled (all tags disabled, or moderator-disabled) for
                    // viewers who can't rate — prevents "rate it, then switch to a
                    // disabled tag to lock the score" abuse. On by default; when
                    // off such topics fall back to the restricted-display mode.
                    if ((bool) $settings->get('tryhackx-topic-rating.hide_disabled_ratings', true)
                        && $policy->isRatingDisabled($discussion)) {
                        return 'hidden';
                    }

                    $mode = (string) $settings->get('tryhackx-topic-rating.display_when_restricted', 'readonly');
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
                    if (!$actor->id) return null;
                    $userRating = Rating::where('discussion_id', $discussion->id)
                        ->where('user_id', $actor->id)
                        ->first();
                    return $userRating ? (int) $userRating->rating : null;
                }),
        ]),

    // Custom API routes
    (new Extend\Routes('api'))
        ->post('/discussion-ratings', 'discussion-ratings.create', Controller\CreateRatingController::class)
        ->delete('/discussion-ratings', 'discussion-ratings.delete', Controller\DeleteRatingController::class)
        ->post('/discussions/{id}/toggle-rating', 'discussions.toggle-rating', Controller\ToggleRatingController::class)
        ->post('/discussions/{id}/reset-ratings', 'discussions.reset-ratings', Controller\ResetRatingsController::class)
        ->get('/discussion-ratings/poll', 'discussion-ratings.poll', Controller\PollRatingController::class)
        ->get('/tryhackx-topic-rating/tag-config', 'tryhackx-topic-rating.tag-config', Controller\GetTagConfigController::class),

    (new Extend\Policy())
        ->modelPolicy(Discussion::class, RatingPolicy::class),

    // Orphan-cleanup: drop tag_config entries when a tag is deleted from
    // flarum/tags. The listener is class-resolved, so it costs nothing
    // when flarum-tags isn't installed (event class simply never fires).
    (new Extend\Event())
        ->listen(\Flarum\Tags\Event\Deleting::class, CleanupTagConfig::class),

    (new Extend\Settings())
        ->serializeToForum('tryhackxTopicRatingEnabled', 'tryhackx-topic-rating.enabled', 'boolval', true)
        ->serializeToForum('tryhackxTopicRatingAllowUnactivated', 'tryhackx-topic-rating.allow_unactivated', 'boolval', false)
        ->serializeToForum('tryhackxTopicRatingShowOnList', 'tryhackx-topic-rating.show_on_list', 'boolval', true)
        ->serializeToForum('tryhackxTopicRatingRateOnList', 'tryhackx-topic-rating.rate_on_list', 'boolval', true)
        ->serializeToForum('tryhackxTopicRatingDisplayWhenRestricted', 'tryhackx-topic-rating.display_when_restricted', 'strval', 'readonly')
        ->serializeToForum('tryhackxTopicRatingBypassGroups', 'tryhackx-topic-rating.bypass_groups', 'strval', '["1"]')
        // Discussion-list rating display style, separately for desktop and mobile.
        ->serializeToForum('tryhackxTopicRatingListStyleDesktop', 'tryhackx-topic-rating.list_style_desktop', $normalizeListStyle)
        ->serializeToForum('tryhackxTopicRatingListStyleMobile', 'tryhackx-topic-rating.list_style_mobile', $normalizeListStyle)
        // Single-star (compact) behaviour: whether the star is clickable to open
        // the ratings modal, and whether that modal lets the user rate.
        ->serializeToForum('tryhackxTopicRatingSingleClickable', 'tryhackx-topic-rating.single_clickable', 'boolval', true)
        ->serializeToForum('tryhackxTopicRatingSingleModalRate', 'tryhackx-topic-rating.single_modal_rate', 'boolval', false)
        // On the discussion list, hide the (empty) widget for people who can't
        // rate when a topic has zero ratings yet. Default false = keep showing.
        ->serializeToForum('tryhackxTopicRatingHideEmptyForNonVoters', 'tryhackx-topic-rating.hide_empty_for_nonvoters', 'boolval', false)
        // Add the rating moderation items (Disable Rating / Reset All Ratings)
        // to the discussion's ⋮ controls menu. Handy for managing ratings when
        // the star widget is hidden. Default false = not shown in the menu.
        ->serializeToForum('tryhackxTopicRatingShowModerationControls', 'tryhackx-topic-rating.show_moderation_controls', 'boolval', false)
        // Shared avatar-display mode (see note at top of file). No ->default().
        ->serializeToForum('tryhackxAvatarModeDesktop', 'tryhackx-avatars.mode_desktop', $normalizeAvatarMode)
        ->serializeToForum('tryhackxAvatarModeMobile', 'tryhackx-avatars.mode_mobile', $normalizeAvatarMode)
        ->default('tryhackx-topic-rating.enabled', true)
        ->default('tryhackx-topic-rating.allow_unactivated', false)
        ->default('tryhackx-topic-rating.show_on_list', true)
        ->default('tryhackx-topic-rating.rate_on_list', true)
        ->default('tryhackx-topic-rating.tag_config', '{}')
        ->default('tryhackx-topic-rating.display_when_restricted', 'readonly')
        ->default('tryhackx-topic-rating.bypass_groups', '["1"]')
        ->default('tryhackx-topic-rating.list_style_desktop', 'full_meta')
        ->default('tryhackx-topic-rating.list_style_mobile', 'full_meta')
        ->default('tryhackx-topic-rating.single_clickable', true)
        ->default('tryhackx-topic-rating.single_modal_rate', false)
        ->default('tryhackx-topic-rating.untagged_enabled', true)
        ->default('tryhackx-topic-rating.hide_empty_for_nonvoters', false)
        ->default('tryhackx-topic-rating.hide_disabled_ratings', true)
        ->default('tryhackx-topic-rating.show_moderation_controls', false),
];
