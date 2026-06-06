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
    ];

    // The single rolling "session" meeting that all check-ins attach to.
    // Created once if it does not exist yet.
    public static function current(): self
    {
        return static::orderBy('id')->first()
            ?? static::create(['title' => 'Attendance', 'is_active' => true, 'radius_meters' => 100]);
    }
}
