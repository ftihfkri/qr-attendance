<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Meeting;
use App\Models\Shareholder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckinController extends Controller
{
    public function show()
    {
        return view('checkin');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'               => ['required', 'string', 'max:150'],
            'koperasi_id'        => ['required', 'string', 'max:100'],
            'phone_number'       => ['required', 'string', 'max:50'],
            'email'              => ['required', 'email', 'max:150'],
            'device_fingerprint' => ['required', 'string', 'max:255'],
            'location_lat'       => ['nullable', 'numeric'],
            'location_lng'       => ['nullable', 'numeric'],
        ]);

        $meeting = Meeting::current();

        // 1. One submission per Koperasi ID for this session.
        if (Attendance::where('meeting_id', $meeting->id)->where('koperasi_id', $data['koperasi_id'])->exists()) {
            return response()->json(['status' => 'error', 'message' => 'This Koperasi ID has already submitted the form.'], 422);
        }

        // 2. One submission per device for this session.
        if (Attendance::where('meeting_id', $meeting->id)->where('device_fingerprint', $data['device_fingerprint'])->exists()) {
            return response()->json(['status' => 'error', 'message' => 'This device has already been used to submit the form.'], 422);
        }

        // 3. Geofence against the venue the admin set (skipped if no venue set).
        $distance = null;
        if ($meeting->venue_lat !== null && $meeting->venue_lng !== null) {
            if (!isset($data['location_lat']) || !isset($data['location_lng'])) {
                return response()->json(['status' => 'error', 'message' => 'Location is required. Please allow location access.'], 422);
            }
            $distance = $this->haversine($data['location_lat'], $data['location_lng'], $meeting->venue_lat, $meeting->venue_lng);
            if ($distance > $meeting->radius_meters) {
                return response()->json([
                    'status'  => 'error',
                    'message' => "You must be within {$meeting->radius_meters} meters of the venue. Current distance: " . round($distance) . 'm',
                ], 422);
            }
        }

        $attendance = DB::transaction(function () use ($data, $meeting, $distance) {
            $shareholder = Shareholder::updateOrCreate(
                ['koperasi_id' => $data['koperasi_id']],
                [
                    'name'         => $data['name'],
                    'phone_number' => $data['phone_number'],
                    'email'        => $data['email'] ?? null,
                ]
            );

            return Attendance::create([
                'meeting_id'          => $meeting->id,
                'shareholder_id'      => $shareholder->id,
                'koperasi_id'         => $data['koperasi_id'],
                'name'                => $data['name'],
                'phone_number'        => $data['phone_number'],
                'date'                => now()->format('Y-m-d'),
                'time'                => now()->format('H:i:s'),
                'location_lat'        => $data['location_lat'] ?? null,
                'location_lng'        => $data['location_lng'] ?? null,
                'device_fingerprint'  => $data['device_fingerprint'],
                'status'              => 'present',
                'distance_from_venue' => $distance,
                'method'              => 'scanned',
            ]);
        });

        return response()->json(['status' => 'success', 'message' => 'Attendance recorded. Thank you!', 'data' => $attendance]);
    }

    private function haversine($lat1, $lng1, $lat2, $lng2): float
    {
        $r = 6371000; // metres
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
