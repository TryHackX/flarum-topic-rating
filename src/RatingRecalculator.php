<?php

namespace TryHackX\TopicRating;

use Flarum\Discussion\Discussion;

/**
 * Recomputes and persists a discussion's denormalised rating aggregates
 * (count / average / last-rated timestamp) from the `discussion_ratings` rows.
 *
 * Extracted out of the `Rating` AbstractModel so the model stays free of raw SQL
 * (data-access convention). It is the one justified raw-query exception in this
 * extension: the aggregates are written in a single atomic UPDATE with correlated
 * subqueries (`discussion_ratings.discussion_id = discussions.id`) so two
 * concurrent rate/unrate requests can't interleave a read-modify-write and leave
 * the cached values out of sync — there is no Eloquent/AbstractModel equivalent
 * for that. The average is stored on the 0–5 scale (DB ratings are 1–10).
 *
 * Resolved from the container and injected into the write controllers.
 */
class RatingRecalculator
{
    public function recalculate(Discussion $discussion): void
    {
        $id = (int) $discussion->id;
        if ($id <= 0) {
            return;
        }

        // Use the discussion's own DB connection directly — Flarum does NOT
        // register Laravel's global facades, so `DB::table()` / `DB::raw()` throw
        // "A facade root has not been set". Going through the base query builder
        // (not Eloquent) also avoids auto-touching discussions.updated_at. The id
        // is bound in the WHERE; the subqueries correlate on discussions.id, so no
        // value is interpolated into the raw SQL.
        $connection = $discussion->getConnection();

        $connection->table('discussions')->where('id', $id)->update([
            'rating_count'   => $connection->raw('(SELECT COUNT(*) FROM discussion_ratings WHERE discussion_id = discussions.id)'),
            'rating_average' => $connection->raw('(SELECT COALESCE(ROUND(AVG(rating) / 2, 2), 0) FROM discussion_ratings WHERE discussion_id = discussions.id)'),
            'last_rated_at'  => $connection->raw('(SELECT MAX(updated_at) FROM discussion_ratings WHERE discussion_id = discussions.id)'),
        ]);
    }
}
