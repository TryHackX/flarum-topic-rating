<?php

namespace TryHackX\TopicRating;

use Flarum\Database\AbstractModel;
use Flarum\Discussion\Discussion;
use Flarum\User\User;

class Rating extends AbstractModel
{
    protected $table = 'discussion_ratings';

    public $timestamps = true;

    public function discussion()
    {
        return $this->belongsTo(Discussion::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Recompute and persist a discussion's denormalised rating aggregates
     * (count / average / last-rated timestamp) from the discussion_ratings
     * rows. Done in a single atomic UPDATE with correlated subqueries so two
     * concurrent rate/unrate requests can't interleave a read-modify-write and
     * leave the cached values out of sync. The average is stored on the 0–5
     * scale (DB ratings are 1–10), matching the rest of the extension.
     */
    public static function recalculate(Discussion $discussion): void
    {
        $id = (int) $discussion->id;
        if ($id <= 0) {
            return;
        }

        // Use the discussion's own DB connection directly — Flarum does NOT
        // register Laravel's global facades, so `DB::table()` / `DB::raw()`
        // throw "A facade root has not been set". Going through the base query
        // builder (not Eloquent) also avoids auto-touching discussions.updated_at.
        $connection = $discussion->getConnection();

        $connection->table('discussions')->where('id', $id)->update([
            'rating_count'   => $connection->raw("(SELECT COUNT(*) FROM discussion_ratings WHERE discussion_id = $id)"),
            'rating_average' => $connection->raw("(SELECT COALESCE(ROUND(AVG(rating) / 2, 2), 0) FROM discussion_ratings WHERE discussion_id = $id)"),
            'last_rated_at'  => $connection->raw("(SELECT MAX(updated_at) FROM discussion_ratings WHERE discussion_id = $id)"),
        ]);
    }
}
