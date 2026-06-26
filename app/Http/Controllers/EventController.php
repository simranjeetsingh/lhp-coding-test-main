<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\CityResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function __construct(private readonly CityResolver $cities) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Events/Index', [
            'filters' => [
                'status' => $request->status,
                'from' => $request->input('from', '2023-01-01'),
            ],
            'statuses' => ['draft', 'published', 'cancelled', 'sold_out'],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        [$events, $stats] = $this->loadListing($request);

        return response()->json([
            'data' => $events->items(),
            'current_page' => $events->currentPage(),
            'last_page' => $events->lastPage(),
            'total' => $events->total(),
            'stats' => $stats,
        ]);
    }

    public function visualOne(Request $request): Response
    {
        return Inertia::render('Events/VisualOne', [
            'cities' => $this->cities->all(),
            'types' => ['concert', 'conference', 'meetup', 'workshop', 'festival', 'sports', 'networking', 'exhibition'],
            'filters' => $this->visualFilters($request),
        ]);
    }

    public function visualTwo(Request $request): Response
    {
        return Inertia::render('Events/VisualTwo', [
            'cities' => $this->cities->all(),
            'types' => ['concert', 'conference', 'meetup', 'workshop', 'festival', 'sports', 'networking', 'exhibition'],
            'filters' => $this->visualFilters($request),
        ]);
    }

    public function visualData(Request $request): JsonResponse
    {
        $perPage = 24;

        $query = Event::query()
            ->withCount('attendees')
            ->where('status', 'published')
            ->when($request->type, fn ($q, $v) => $q->where('type', $v))
            ->when($request->date_from, fn ($q, $v) => $q->where('created_time', '>=', strtotime($v)))
            ->when($request->date_to, fn ($q, $v) => $q->where('created_time', '<=', strtotime($v) + 86399))
            ->when($request->city, function ($q, $cityName) {
                $city = collect($this->cities->all())->firstWhere('name', $cityName);
                if ($city) {
                    $q->whereBetween('latitude', [$city['lat'] - 1.0, $city['lat'] + 1.0])
                      ->whereBetween('longitude', [$city['lng'] - 1.0, $city['lng'] + 1.0]);
                }
            });

        $sort = $request->input('sort', 'asc');
        $query->orderBy('created_time', $sort === 'desc' ? 'desc' : 'asc');

        $paginated = $query->paginate($perPage)->withQueryString();

        $data = collect($paginated->items())->map(fn (Event $e) => $this->formatForVisual($e));

        return response()->json([
            'data' => $data,
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
        ]);
    }

    public function show(Event $event): Response
    {
        $event->load(['images', 'attendees']);
        $payload = $event->payload;

        return Inertia::render('Events/Show', [
            'event' => [
                'id' => $event->id,
                'name' => $payload['name'] ?? 'Untitled Event',
                'description' => $payload['description'] ?? '',
                'type' => $event->type,
                'status' => $event->status,
                'starts_at' => (int) ($payload['schedule']['starts_at'] ?? $event->created_time),
                'ends_at' => (int) ($payload['schedule']['ends_at'] ?? 0),
                'location' => $this->cities->nearest((float) $event->latitude, (float) $event->longitude),
                'venue' => $payload['venue']['name'] ?? '',
                'venue_capacity' => (int) ($payload['venue']['capacity'] ?? 0),
                'price' => (float) ($payload['pricing']['min_price'] ?? 0),
                'currency' => $payload['pricing']['currency'] ?? 'USD',
                'organizer' => $payload['organizer']['name'] ?? '',
                'tags' => $payload['tags'] ?? [],
                'images' => $event->computed_images,
                'attendee_count' => $event->attendees->count(),
            ],
        ]);
    }

    private function formatForVisual(Event $event): array
    {
        $payload = $event->payload;

        return [
            'id' => $event->id,
            'name' => $payload['name'] ?? 'Untitled Event',
            'description' => $payload['description'] ?? '',
            'type' => $event->type,
            'status' => $event->status,
            'starts_at' => (int) ($payload['schedule']['starts_at'] ?? $event->created_time),
            'ends_at' => (int) ($payload['schedule']['ends_at'] ?? 0),
            'location' => $this->cities->nearest((float) $event->latitude, (float) $event->longitude),
            'venue' => $payload['venue']['name'] ?? '',
            'price' => (float) ($payload['pricing']['min_price'] ?? 0),
            'currency' => $payload['pricing']['currency'] ?? 'USD',
            'latitude' => $event->latitude,
            'longitude' => $event->longitude,
            'images' => $event->computed_images,
            'attendee_count' => $event->attendees_count,
        ];
    }

    /** @return array{0: LengthAwarePaginator, 1: array{ms: int, bytes: int}} */
    private function loadListing(Request $request): array
    {
        $start = microtime(true);

        $events = Event::with('user')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_time')
            ->paginate(50)
            ->withQueryString();

        $stats = [
            'ms' => (int) round((microtime(true) - $start) * 1000),
            'bytes' => strlen((string) json_encode($events->items())),
        ];

        return [$events, $stats];
    }

    private function visualFilters(Request $request): array
    {
        return [
            'type' => $request->type ?? '',
            'city' => $request->city ?? '',
            'date_from' => $request->date_from ?? '',
            'date_to' => $request->date_to ?? '',
        ];
    }
}
