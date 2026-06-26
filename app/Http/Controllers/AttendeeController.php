<?php

namespace App\Http\Controllers;

use App\Mail\AttendanceConfirmedMail;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Services\CityResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AttendeeController extends Controller
{
    public function __construct(private readonly CityResolver $cities) {}

    public function store(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $attendee = EventAttendee::firstOrCreate(
            ['event_id' => $event->id, 'email' => $validated['email']],
            ['name' => $validated['name']],
        );

        if ($attendee->wasRecentlyCreated) {
            $payload = $event->payload;
            $eventData = [
                'name' => $payload['name'] ?? 'Untitled Event',
                'starts_at' => (int) ($payload['schedule']['starts_at'] ?? $event->created_time),
                'venue' => $payload['venue']['name'] ?? '',
                'location' => $this->cities->nearest((float) $event->latitude, (float) $event->longitude),
            ];

            Mail::to($attendee->email)->queue(
                new AttendanceConfirmedMail($attendee->name, $eventData),
            );
        }

        return response()->json([
            'message' => $attendee->wasRecentlyCreated
                ? 'You are registered! Check your email for confirmation.'
                : 'You are already registered for this event.',
        ], $attendee->wasRecentlyCreated ? 201 : 200);
    }
}
