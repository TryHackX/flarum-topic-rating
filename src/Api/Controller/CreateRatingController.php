<?php

namespace TryHackX\TopicRating\Api\Controller;

use TryHackX\TopicRating\Rating;
use Flarum\Discussion\Discussion;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CreateRatingController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        $data = Arr::get($request->getParsedBody(), 'data.attributes', []);
        $discussionId = intval(Arr::get($data, 'discussionId', 0));
        $ratingValue = intval(Arr::get($data, 'rating', 0));

        if ($ratingValue < 1 || $ratingValue > 10) {
            throw new \Flarum\Foundation\ValidationException([
                'rating' => 'Rating must be between 1 and 10 (0.5-5.0 stars).',
            ]);
        }

        $discussion = Discussion::findOrFail($discussionId);

        $actor->assertCan('rate', $discussion);

        if ($discussion->rating_disabled) {
            throw new \Flarum\Foundation\ValidationException([
                'rating' => 'Rating is disabled for this discussion.',
            ]);
        }

        $rating = Rating::where('discussion_id', $discussionId)
            ->where('user_id', $actor->id)
            ->first();

        if ($rating) {
            $rating->rating = $ratingValue;
            $rating->save();
        } else {
            $rating = new Rating();
            $rating->discussion_id = $discussionId;
            $rating->user_id = $actor->id;
            $rating->rating = $ratingValue;
            $rating->save();
        }

        $this->recalculate($discussion);

        return new JsonResponse([
            'rating' => (int) $rating->rating,
            'discussionId' => $discussionId,
        ]);
    }

    protected function recalculate(Discussion $discussion): void
    {
        $ratings = Rating::where('discussion_id', $discussion->id);
        $discussion->rating_count = $ratings->count();
        $discussion->rating_average = $discussion->rating_count > 0
            ? round($ratings->avg('rating') / 2, 2)
            : 0;
        $discussion->last_rated_at = Rating::where('discussion_id', $discussion->id)
            ->max('updated_at');
        $discussion->save();
    }
}
