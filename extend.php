<?php

use TryHackX\TopicRating\Api\Controller;
use TryHackX\TopicRating\Api\Resource\RatingResource;
use TryHackX\TopicRating\Access\RatingPolicy;
use TryHackX\TopicRating\Rating;
use Flarum\Api\Resource\DiscussionResource;
use Flarum\Api\Context;
use Flarum\Api\Schema;
use Flarum\Discussion\Discussion;
use Flarum\Extend;

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
                    if ($actor->id && !$actor->is_email_confirmed
                        && !$actor->can('rate', $discussion)
                        && !$discussion->rating_disabled
                    ) {
                        $settings = resolve(\Flarum\Settings\SettingsRepositoryInterface::class);
                        return !(bool) $settings->get('tryhackx-topic-rating.allow_unactivated', false);
                    }
                    return false;
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
        ->get('/discussion-ratings/poll', 'discussion-ratings.poll', Controller\PollRatingController::class),

    (new Extend\Policy())
        ->modelPolicy(Discussion::class, RatingPolicy::class),

    (new Extend\Settings())
        ->serializeToForum('tryhackxTopicRatingEnabled', 'tryhackx-topic-rating.enabled', 'boolval', true)
        ->serializeToForum('tryhackxTopicRatingAllowUnactivated', 'tryhackx-topic-rating.allow_unactivated', 'boolval', false)
        ->default('tryhackx-topic-rating.enabled', true)
        ->default('tryhackx-topic-rating.allow_unactivated', false),
];
