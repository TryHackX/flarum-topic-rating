<?php

namespace TryHackX\TopicRating\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Group\Group;
use PHPUnit\Framework\Attributes\Test;
use TryHackX\TopicRating\Tests\integration\RatingTestCase;

/**
 * Group-based rules (bypass groups, per-tag "groups" state) read the actor's groups
 * the way core does for permissions: the implicit Members group counts, and a
 * suspended user (flarum/suspend) counts as a guest — so a suspension also stops
 * rating through a group rule, not only through the Rate permission.
 */
class GroupRulesTest extends RatingTestCase
{
    protected const TRUSTED = 5;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-suspend', 'tryhackx-topic-rating');

        $this->prepareDatabase([
            Group::class => [
                ['id' => self::TRUSTED, 'name_singular' => 'Trusted', 'name_plural' => 'Trusted'],
            ],
            'group_user' => [
                ['user_id' => self::MEMBER2, 'group_id' => self::TRUSTED],
            ],
        ]);
    }

    protected function bypassGroups(array $ids): void
    {
        $this->setting('tryhackx-topic-rating.bypass_groups', json_encode(array_map('strval', $ids)));
    }

    #[Test]
    public function members_picked_as_a_bypass_group_can_rate_without_the_rate_permission(): void
    {
        // Settings first: they are written when the test app boots (first database() call).
        $this->bypassGroups([Group::ADMINISTRATOR_ID, Group::MEMBER_ID]);
        $this->database()->table('group_permission')->where('permission', 'discussion.rate')->delete();

        $this->assertRated($this->rate(self::MEMBER, self::REVIEW, 8));
    }

    #[Test]
    public function a_suspended_user_cannot_rate_through_a_bypass_group(): void
    {
        $this->bypassGroups([Group::ADMINISTRATOR_ID, self::TRUSTED]);

        // Not suspended yet: the bypass group lets them rate.
        $this->assertRated($this->rate(self::MEMBER2, self::REVIEW, 7));

        $this->database()->table('users')->where('id', self::MEMBER2)
            ->update(['suspended_until' => Carbon::now()->addDay()]);

        $this->assertSame(403, $this->rate(self::MEMBER2, self::OTHER, 7)->getStatusCode());
    }
}
