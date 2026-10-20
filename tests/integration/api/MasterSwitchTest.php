<?php

namespace TryHackX\TopicRating\Tests\integration\api;

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;
use TryHackX\TopicRating\Tests\integration\RatingTestCase;

/**
 * The admin's master switch, `tryhackx-topic-rating.enabled` ("Enable Topic
 * Rating", default on). It used to be saved and serialized but read nowhere.
 * Off: nobody rates — admins / bypass groups included — and the widget is hidden;
 * the stored ratings are kept and come back when it is switched on again.
 */
class MasterSwitchTest extends RatingTestCase
{
    protected const UNCONFIRMED = 5;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareDatabase([
            User::class => [
                ['id' => self::UNCONFIRMED, 'username' => 'fresh', 'email' => 'fresh@machine.local', 'is_email_confirmed' => 0],
            ],
        ]);
    }

    /** Change the switch after boot (setting() only applies before the app starts). */
    protected function enabled(string $value): void
    {
        $this->app()->getContainer()->make(SettingsRepositoryInterface::class)->set('tryhackx-topic-rating.enabled', $value);
    }

    #[Test]
    public function on_by_default(): void
    {
        $this->assertRated($this->rate(self::MEMBER, self::REVIEW, 8));
        $this->assertSame('rate', $this->discussion(self::REVIEW, self::MEMBER)['ratingDisplayMode']);
    }

    #[Test]
    public function switched_off_nobody_rates_and_the_widget_is_hidden(): void
    {
        $this->assertRated($this->rate(self::MEMBER, self::REVIEW, 8));
        $this->enabled('0');

        $this->assertSame(403, $this->rate(self::MEMBER2, self::REVIEW, 6)->getStatusCode());
        $this->assertSame(403, $this->rate(self::ADMIN, self::REVIEW, 6)->getStatusCode(), 'the Admin bypass group too');
        $this->assertSame(403, $this->unrate(self::MEMBER, self::REVIEW)->getStatusCode(), 'and nobody can remove one');

        foreach ([self::MEMBER, self::ADMIN, null] as $viewer) {
            $attributes = $this->discussion(self::REVIEW, $viewer);
            $this->assertFalse($attributes['canRate']);
            $this->assertSame('hidden', $attributes['ratingDisplayMode']);
        }

        // Activating the account wouldn't help either.
        $this->assertFalse($this->discussion(self::REVIEW, self::UNCONFIRMED)['canRateRequiresActivation']);

        // The stored score stays as it was.
        $this->assertSame(1, (int) $this->row(self::REVIEW)->rating_count);
    }

    #[Test]
    public function switched_on_again_the_ratings_are_back(): void
    {
        $this->assertRated($this->rate(self::MEMBER, self::REVIEW, 8));
        $this->enabled('0');
        $this->enabled('1');

        $attributes = $this->discussion(self::REVIEW, self::MEMBER);
        $this->assertSame('rate', $attributes['ratingDisplayMode']);
        $this->assertSame(1, $attributes['ratingCount']);
        $this->assertSame(8, $attributes['userRating']);
        $this->assertRated($this->rate(self::MEMBER2, self::REVIEW, 6));
    }

    #[Test]
    public function a_switched_off_boolean_saved_as_an_empty_string_counts_as_off(): void
    {
        $this->enabled('');

        $this->assertSame(403, $this->rate(self::MEMBER, self::REVIEW, 8)->getStatusCode());
        $this->assertSame('hidden', $this->discussion(self::REVIEW, self::MEMBER)['ratingDisplayMode']);
    }
}
