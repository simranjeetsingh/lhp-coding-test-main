<?php

use App\Console\Commands\SendEventReminders;
use App\Mail\EventReminderMail;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('queues a 3-day reminder for attendees of events starting in 3 days', function () {
    Mail::fake();

    $startsAt = now()->addDays(3)->timestamp;
    $event = Event::factory()->for(User::factory()->create())->create([
        'payload' => ['name' => 'Test Event', 'schedule' => ['starts_at' => $startsAt, 'ends_at' => $startsAt + 7200], 'venue' => ['name' => 'The Venue', 'capacity' => 500], 'pricing' => ['currency' => 'USD', 'min_price' => 0]],
        'created_time' => $startsAt,
    ]);

    EventAttendee::create(['event_id' => $event->id, 'name' => 'Alice', 'email' => 'alice@example.com']);

    $this->artisan(SendEventReminders::class)->assertSuccessful();

    Mail::assertQueued(EventReminderMail::class, fn ($mail) =>
        $mail->hasTo('alice@example.com') && $mail->reminderType === '3d'
    );

    expect(EventAttendee::first()->reminded_3d_at)->not->toBeNull();
});

it('queues a 24-hour reminder for attendees of events starting in 24 hours', function () {
    Mail::fake();

    $startsAt = now()->addHours(24)->timestamp;
    $event = Event::factory()->for(User::factory()->create())->create([
        'payload' => ['name' => 'Test Event', 'schedule' => ['starts_at' => $startsAt, 'ends_at' => $startsAt + 7200], 'venue' => ['name' => 'The Venue', 'capacity' => 500], 'pricing' => ['currency' => 'USD', 'min_price' => 0]],
        'created_time' => $startsAt,
    ]);

    EventAttendee::create(['event_id' => $event->id, 'name' => 'Bob', 'email' => 'bob@example.com']);

    $this->artisan(SendEventReminders::class)->assertSuccessful();

    Mail::assertQueued(EventReminderMail::class, fn ($mail) =>
        $mail->hasTo('bob@example.com') && $mail->reminderType === '24h'
    );

    expect(EventAttendee::first()->reminded_24h_at)->not->toBeNull();
});

it('does not send a reminder twice for the same window', function () {
    Mail::fake();

    $startsAt = now()->addDays(3)->timestamp;
    $event = Event::factory()->for(User::factory()->create())->create([
        'payload' => ['name' => 'Test Event', 'schedule' => ['starts_at' => $startsAt, 'ends_at' => $startsAt + 7200], 'venue' => ['name' => 'The Venue', 'capacity' => 500], 'pricing' => ['currency' => 'USD', 'min_price' => 0]],
        'created_time' => $startsAt,
    ]);

    EventAttendee::create(['event_id' => $event->id, 'name' => 'Alice', 'email' => 'alice@example.com', 'reminded_3d_at' => now()]);

    $this->artisan(SendEventReminders::class)->assertSuccessful();

    Mail::assertNothingQueued();
});
