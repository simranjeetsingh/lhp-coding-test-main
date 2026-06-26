<?php

namespace App\Console\Commands;

use App\Mail\EventReminderMail;
use App\Models\EventAttendee;
use App\Services\CityResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendEventReminders extends Command
{
    protected $signature = 'events:send-reminders';

    protected $description = 'Send 3-day and 24-hour reminder emails for upcoming events';

    public function __construct(private readonly CityResolver $cities)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $now = now();

        $windows = [
            '3d' => [$now->copy()->addDays(3)->subMinutes(30), $now->copy()->addDays(3)->addMinutes(30)],
            '24h' => [$now->copy()->addHours(24)->subMinutes(30), $now->copy()->addHours(24)->addMinutes(30)],
        ];

        foreach ($windows as $type => [$from, $to]) {
            $column = $type === '3d' ? 'reminded_3d_at' : 'reminded_24h_at';

            $attendees = EventAttendee::with('event')
                ->whereNull($column)
                ->whereHas('event', fn ($q) => $q
                    ->whereBetween('created_time', [$from->timestamp, $to->timestamp])
                )
                ->get();

            foreach ($attendees as $attendee) {
                $event = $attendee->event;
                $payload = $event->payload;

                $eventData = [
                    'name' => $payload['name'] ?? 'Untitled Event',
                    'starts_at' => (int) ($payload['schedule']['starts_at'] ?? $event->created_time),
                    'venue' => $payload['venue']['name'] ?? '',
                    'location' => $this->cities->nearest((float) $event->latitude, (float) $event->longitude),
                ];

                Mail::to($attendee->email)->queue(
                    new EventReminderMail($attendee->name, $eventData, $type),
                );

                $attendee->update([$column => now()]);
            }

            $this->info("Queued {$attendees->count()} {$type} reminders.");
        }
    }
}
