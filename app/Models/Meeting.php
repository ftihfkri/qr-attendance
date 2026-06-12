<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Meeting extends Model
{
    protected $fillable = [
        'title',
        'meeting_date',
        'venue_name',
        'venue_lat',
        'venue_lng',
        'radius_meters',
        'is_active',
        'submission_open',
        'opens_at',
        'closes_at',
    ];

    protected $casts = [
        'submission_open' => 'boolean',
        'opens_at'        => 'datetime',
        'closes_at'       => 'datetime',
    ];

    // The single rolling "session" meeting that all check-ins attach to.
    // Created once if it does not exist yet.
    public static function current(): self
    {
        return static::orderBy('id')->first()
            ?? static::create(['title' => 'Attendance', 'is_active' => true, 'radius_meters' => 100, 'submission_open' => true]);
    }

    // Whether the check-in form is currently accepting submissions: the manual
    // switch must be on AND (if a schedule is set) the current time must be
    // inside the opens_at..closes_at window.
    public function acceptingSubmissions(): bool
    {
        if (!$this->submission_open) {
            return false;
        }
        $now = now();
        if ($this->opens_at && $now->lt($this->opens_at)) {
            return false;
        }
        if ($this->closes_at && $now->gt($this->closes_at)) {
            return false;
        }
        return true;
    }

    // A human-readable reason shown to shareholders when the form is closed.
    public function closedReason(): string
    {
        if (!$this->submission_open) {
            return 'Check-in is currently closed by the organiser.';
        }
        if ($this->opens_at && now()->lt($this->opens_at)) {
            return 'Check-in opens at ' . $this->opens_at->format('d M Y, g:i A') . '.';
        }
        if ($this->closes_at && now()->gt($this->closes_at)) {
            return 'Check-in closed at ' . $this->closes_at->format('d M Y, g:i A') . '.';
        }
        return 'Check-in is currently closed.';
    }
}
