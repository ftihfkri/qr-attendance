<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $table = 'attendance';

    protected $fillable = [
        'meeting_id',
        'shareholder_id',
        'koperasi_id',
        'name',
        'phone_number',
        'date',
        'time',
        'location_lat',
        'location_lng',
        'device_fingerprint',
        'status',
        'distance_from_venue',
        'method',
        'custom_data',
    ];

    protected $casts = [
        'custom_data' => 'array',
    ];

    public function shareholder()
    {
        return $this->belongsTo(Shareholder::class);
    }
}
