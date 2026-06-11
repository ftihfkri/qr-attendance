<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Meeting;
use App\Models\Membership;
use App\Models\Shareholder;
use App\Support\SpreadsheetReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function dashboard()
    {
        return view('admin.dashboard', ['meeting' => Meeting::current()]);
    }

    public function verify()
    {
        return view('admin.verify');
    }

    public function getVenue()
    {
        $m = Meeting::current();
        return response()->json(['status' => 'success', 'data' => [
            'venue_name'    => $m->venue_name,
            'venue_lat'     => $m->venue_lat,
            'venue_lng'     => $m->venue_lng,
            'radius_meters' => $m->radius_meters,
        ]]);
    }

    public function setVenue(Request $request)
    {
        $data = $request->validate([
            'venue_lat'     => ['required', 'numeric'],
            'venue_lng'     => ['required', 'numeric'],
            'radius_meters' => ['nullable', 'integer', 'min:10'],
            'venue_name'    => ['nullable', 'string', 'max:150'],
        ]);

        $m = Meeting::current();
        $m->update([
            'venue_lat'     => $data['venue_lat'],
            'venue_lng'     => $data['venue_lng'],
            'radius_meters' => $data['radius_meters'] ?? $m->radius_meters,
            'venue_name'    => $data['venue_name'] ?? $m->venue_name,
        ]);

        return response()->json(['status' => 'success', 'data' => [
            'venue_lat' => $m->venue_lat, 'venue_lng' => $m->venue_lng, 'radius_meters' => $m->radius_meters,
        ]]);
    }

    public function attendanceList()
    {
        $m = Meeting::current();
        $rows = Attendance::with('shareholder')->where('meeting_id', $m->id)->orderByDesc('created_at')->get();
        $data = $rows->map(fn ($r) => [
            'id'           => $r->id,
            'name'         => $r->name,
            'koperasi_id'  => $r->koperasi_id,
            'phone_number' => $r->phone_number,
            'email'        => optional($r->shareholder)->email,
            'date'         => $r->date,
            'time'         => $r->time,
            'method'       => $r->method,
        ]);
        return response()->json(['status' => 'success', 'data' => $data]);
    }

    // Download the list as CSV (opens directly in Excel).
    public function export()
    {
        $m = Meeting::current();
        $rows = Attendance::with('shareholder')->where('meeting_id', $m->id)->orderBy('created_at')->get();

        $filename = 'attendance_' . now()->format('Y-m-d') . '.csv';
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($rows) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel reads accents correctly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['No', 'Full Name', 'Koperasi ID', 'Phone', 'Email', 'Date', 'Time', 'Method']);
            $i = 1;
            foreach ($rows as $r) {
                fputcsv($out, [
                    $i++, $r->name, $r->koperasi_id, $r->phone_number,
                    optional($r->shareholder)->email, $r->date, $r->time, $r->method,
                ]);
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function clear()
    {
        $m = Meeting::current();
        $count = Attendance::where('meeting_id', $m->id)->delete();
        return response()->json(['status' => 'success', 'deleted' => $count]);
    }

    // Edit a submitted record (admin or staff). Errors if the new Koperasi ID
    // overlaps another submission in the same meeting.
    public function updateAttendance(Request $request, $id)
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:150'],
            'koperasi_id'  => ['required', 'string', 'max:100'],
            'phone_number' => ['required', 'string', 'max:50'],
            'email'        => ['required', 'email', 'max:150'],
        ]);

        $att = Attendance::find($id);
        if (!$att) {
            return response()->json(['status' => 'error', 'message' => 'Record not found.'], 404);
        }

        // Overlap check: another submission in this meeting already uses the new ID.
        $overlap = Attendance::where('meeting_id', $att->meeting_id)
            ->where('koperasi_id', $data['koperasi_id'])
            ->where('id', '!=', $att->id)
            ->exists();
        if ($overlap) {
            return response()->json(['status' => 'error', 'message' => 'Another submission already uses this Koperasi ID.'], 422);
        }

        try {
            DB::transaction(function () use ($att, $data) {
                if ($att->shareholder) {
                    $att->shareholder->update([
                        'name'         => $data['name'],
                        'phone_number' => $data['phone_number'],
                        'email'        => $data['email'],
                        'koperasi_id'  => $data['koperasi_id'],
                    ]);
                }
                $att->update([
                    'name'         => $data['name'],
                    'phone_number' => $data['phone_number'],
                    'koperasi_id'  => $data['koperasi_id'],
                ]);
            });
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => 'That Koperasi ID is already in use.'], 422);
        }

        return response()->json(['status' => 'success', 'message' => 'Record updated.']);
    }

    // Admin manually adds a person (bypasses geofence/device; still one per Koperasi ID).
    public function manualAdd(Request $request)
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:150'],
            'koperasi_id'  => ['required', 'string', 'max:100'],
            'phone_number' => ['required', 'string', 'max:50'],
            'email'        => ['required', 'email', 'max:150'],
        ]);

        $m = Meeting::current();
        if (Attendance::where('meeting_id', $m->id)->where('koperasi_id', $data['koperasi_id'])->exists()) {
            return response()->json(['status' => 'error', 'message' => 'This Koperasi ID has already been recorded.'], 422);
        }

        DB::transaction(function () use ($data, $m) {
            $shareholder = Shareholder::updateOrCreate(
                ['koperasi_id' => $data['koperasi_id']],
                ['name' => $data['name'], 'phone_number' => $data['phone_number'], 'email' => $data['email'] ?? null]
            );
            Attendance::create([
                'meeting_id'         => $m->id,
                'shareholder_id'     => $shareholder->id,
                'koperasi_id'        => $data['koperasi_id'],
                'name'               => $data['name'],
                'phone_number'       => $data['phone_number'],
                'date'               => now()->format('Y-m-d'),
                'time'               => now()->format('H:i:s'),
                'device_fingerprint' => 'manual:' . $data['koperasi_id'],
                'status'             => 'present',
                'method'             => 'manual',
            ]);
        });

        return response()->json(['status' => 'success', 'message' => 'Added to the list.']);
    }

    // Bulk-import the membership roster from an .xlsx / .csv file with two
    // columns (name, membership_id). Existing member_ids are skipped, not
    // overwritten. Returns { added, skipped, errors }.
    public function membershipUpload(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5120'], // 5 MB
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['xlsx', 'csv', 'txt'], true)) {
            return response()->json(['status' => 'error', 'message' => 'Please upload a .xlsx or .csv file.'], 422);
        }

        try {
            $rows = $ext === 'xlsx'
                ? SpreadsheetReader::xlsx($file->getRealPath())
                : SpreadsheetReader::csv($file->getRealPath());
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => 'Could not read the file: ' . $e->getMessage()], 422);
        }

        if (empty($rows)) {
            return response()->json(['status' => 'error', 'message' => 'The file is empty.'], 422);
        }

        // Skip a header row if the first row looks like column titles.
        $firstName = strtolower(preg_replace('/[\s_]/', '', (string) ($rows[0][0] ?? '')));
        $firstId   = strtolower(preg_replace('/[\s_]/', '', (string) ($rows[0][1] ?? '')));
        $hasHeader = str_contains($firstName, 'name') || preg_match('/member|^id$/', $firstId);
        $dataRows  = $hasHeader ? array_slice($rows, 1) : $rows;

        $added = 0;
        $skipped = 0;
        $errors = [];
        $seen = [];

        foreach ($dataRows as $i => $row) {
            $rowNum = $hasHeader ? $i + 2 : $i + 1; // 1-based, for readable errors
            $name = trim((string) ($row[0] ?? ''));
            $memberId = trim((string) ($row[1] ?? ''));

            if ($name === '' && $memberId === '') {
                continue; // blank line
            }
            if ($name === '' || $memberId === '') {
                $errors[] = "Row {$rowNum}: missing " . ($name === '' ? 'name' : 'membership_id');
                continue;
            }
            if (isset($seen[$memberId])) {
                $skipped++; // duplicate within the file
                continue;
            }
            $seen[$memberId] = true;

            if (Membership::where('member_id', $memberId)->exists()) {
                $skipped++;
                continue;
            }

            Membership::create(['name' => $name, 'member_id' => $memberId]);
            $added++;
        }

        return response()->json(['status' => 'success', 'data' => compact('added', 'skipped', 'errors')]);
    }
}
