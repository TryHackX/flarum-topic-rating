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

class ResetRatingsController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        $discussionId = Arr::get($request->getQueryParams(), 'id');
        $discussion = Discussion::findOrFail($discussionId);

        $actor->assertCan('discussion.rate.reset');

        Rating::where('discussion_id', $discussion->id)->delete();

        Rating::recalculate($discussion);

        return new JsonResponse([
            'success' => true,
        ]);
    }
}
