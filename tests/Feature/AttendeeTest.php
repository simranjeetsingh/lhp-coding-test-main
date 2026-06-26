<?php

use App\Mail\AttendanceConfirmedMail;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('registers a new attendee and queues a confirmation email', function () {
    Mail::fake();

    $event = Event::factory()->for(User::factory()->create())->create();

    $this->postJson(route('events.attendees.store', $event), [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ])->assertCreated()
      ->assertJson(['message' => 'You are registered! Check your email for confirmation.']);

    expect(EventAttendee::where('event_id', $event->id)->where('email', 'jane@example.com')->exists())->toBeTrue();

    Mail::assertQueued(AttendanceConfirmedMail::class, fn ($mail) => $mail->hasTo('jane@example.com'));
});

it('does not duplicate an attendee who registers twice', function () {
    Mail::fake();

    $event = Event::factory()->for(User::factory()->create())->create();

    $payload = ['name' => 'Jane Doe', 'email' => 'jane@example.com'];

    $this->postJson(route('events.attendees.store', $event), $payload)->assertCreated();
    $this->postJson(route('events.attendees.store', $event), $payload)->assertOk()
         ->assertJson(['message' => 'You are already registered for this event.']);

    expect(EventAttendee::where('event_id', $event->id)->count())->toBe(1);

    Mail::assertQueued(AttendanceConfirmedMail::class, 1);
});

it('validates required attendee fields', function () {
    $event = Event::factory()->for(User::factory()->create())->create();

    // shouldRenderJsonWhen is configured for api/* only, so web validation failures redirect back
    $this->post(route('events.attendees.store', $event), [])
         ->assertRedirect()
         ->assertSessionHasErrors(['name', 'email']);
});
