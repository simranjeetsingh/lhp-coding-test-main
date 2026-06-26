<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function newUniqueId(): string
    {
        return (string) Str::uuid();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(EventImage::class)->orderBy('sort_order');
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(EventAttendee::class);
    }

    /**
     * Returns stored images if any, otherwise derives 2 placeholder images
     * from the event UUID so every event has visuals without backfilling.
     *
     * @return string[]
     */
    public function getComputedImagesAttribute(): array
    {
        if ($this->relationLoaded('images') && $this->images->isNotEmpty()) {
            return $this->images->pluck('path')->all();
        }

        $hash = hexdec(substr(str_replace('-', '', $this->id), 0, 8));
        $total = 8;
        $a = ($hash % $total) + 1;
        $b = (($hash >> 4) % $total) + 1;
        if ($b === $a) {
            $b = ($b % $total) + 1;
        }

        return ["/images/events/{$a}.svg", "/images/events/{$b}.svg"];
    }
}
