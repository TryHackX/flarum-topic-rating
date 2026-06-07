<?php

namespace TryHackX\TopicRating\Api\Controller;

use TryHackX\TopicRating\Rating;
use Flarum\Discussion\Discussion;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DeleteRatingController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        $data = Arr::get($request->getParsedBody(), 'data.attributes', []);
        $discussionId = intval(Arr::get($data, 'discussionId', 0));

        $rating = Rating::where('discussion_id', $discussionId)
            ->where('user_id', $actor->id)
            ->firstOrFail();

        $discussion = Discussion::whereVisibleTo($actor)->findOrFail($discussionId);
        $actor->assertCan('rate', $discussion);

        $rating->delete();

        Rating::recalculate($discussion);

        return new EmptyResponse(204);
    }
}
