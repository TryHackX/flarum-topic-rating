<?php

namespace TryHackX\TopicRating\Api\Resource;

use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Illuminate\Database\Eloquent\Builder;
use Tobyz\JsonApiServer\Context;
use TryHackX\TopicRating\Rating;

/**
 * @extends AbstractDatabaseResource<Rating>
 */
class RatingResource extends AbstractDatabaseResource
{
    public function type(): string
    {
        return 'discussion-ratings';
    }

    public function model(): string
    {
        return Rating::class;
    }

    public function scope(Builder $query, Context $context): void
    {
        $params = $context->request->getQueryParams();
        $discussionId = intval(
            $params['discussion_id']
            ?? $params['filter']['discussion_id']
            ?? 0
        );

        if ($discussionId > 0) {
            $query->where('discussion_id', $discussionId);
        }
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->defaultSort('-createdAt')
                ->defaultInclude(['user'])
                ->paginate(10, 50),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Integer::make('rating')
                ->get(fn (Rating $rating) => (int) $rating->rating),
            Schema\DateTime::make('createdAt'),
            Schema\DateTime::make('updatedAt'),
            Schema\Relationship\ToOne::make('user')
                ->type('users')
                ->includable(),
            Schema\Relationship\ToOne::make('discussion')
                ->type('discussions')
                ->includable(),
        ];
    }

    public function sorts(): array
    {
        return [
            SortColumn::make('createdAt'),
        ];
    }
}
