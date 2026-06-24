<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Meeting;
use App\Models\Membership;
use App\Models\Shareholder;
use App\Support\SpreadsheetReader;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

    // Current submission-window settings for the check-in form.
    public function getSubmission()
    {
        $m = Meeting::current();
        return response()->json(['status' => 'success', 'data' => [
            'submission_open' => $m->submission_open,
            'accepting'       => $m->acceptingSubmissions(),
            'opens_at'        => optional($m->opens_at)->format('Y-m-d\TH:i'),
            'closes_at'       => optional($m->closes_at)->format('Y-m-d\TH:i'),
        ]]);
    }

    // Open/close the form manually and/or set an opens_at..closes_at schedule.
    // Times are interpreted in the app timezone (Asia/Kuala_Lumpur).
    public function setSubmission(Request $request)
    {
        $data = $request->validate([
            'submission_open' => ['nullable', 'boolean'],
            'opens_at'        => ['nullable', 'date'],
            'closes_at'       => ['nullable', 'date'],
        ]);

        $m = Meeting::current();

        if ($request->has('submission_open') && $data['submission_open'] !== null) {
            $m->submission_open = (bool) $data['submission_open'];
        }
        if ($request->has('opens_at')) {
            $m->opens_at = $data['opens_at'] ? Carbon::parse($data['opens_at']) : null;
        }
        if ($request->has('closes_at')) {
            $m->closes_at = $data['closes_at'] ? Carbon::parse($data['closes_at']) : null;
        }

        if ($m->opens_at && $m->closes_at && $m->closes_at->lte($m->opens_at)) {
            return response()->json(['status' => 'error', 'message' => 'Close time must be after open time.'], 422);
        }

        $m->save();
        return $this->getSubmission();
    }

    // Current check-in form configuration (required fields + custom columns).
    public function getFormConfig()
    {
        return response()->json(['status' => 'success', 'data' => Meeting::current()->formConfig()]);
    }

    // Save which fields are required and the custom columns (admin + staff).
    public function setFormConfig(Request $request)
    {
        $data = $request->validate([
            'phone_required'    => ['required', 'boolean'],
            'email_required'    => ['required', 'boolean'],
            'custom'            => ['array'],
            'custom.*.label'    => ['required', 'string', 'max:60'],
            'custom.*.required' => ['boolean'],
        ]);

        $custom = [];
        $seen   = [];
        foreach ($data['custom'] ?? [] as $f) {
            $label = trim($f['label']);
            if ($label === '') {
                continue;
            }
            $key = \Illuminate\Support\Str::slug($label, '_') ?: 'field';
            $base = $key;
            $n = 1;
            while (isset($seen[$key])) {
                $key = $base . '_' . (++$n);
            }
            $seen[$key] = true;
            $custom[] = ['key' => $key, 'label' => $label, 'required' => (bool) ($f['required'] ?? false)];
        }

        $m = Meeting::current();
        $m->form_config = [
            'phone_required' => (bool) $data['phone_required'],
            'email_required' => (bool) $data['email_required'],
            'custom'         => $custom,
        ];
        $m->save();

        return response()->json(['status' => 'success', 'message' => 'Form settings saved.', 'data' => $m->formConfig()]);
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

        $custom = $m->formConfig()['custom']; // [{key,label,required}]

        $callback = function () use ($rows, $custom) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel reads accents correctly.
            fwrite($out, "\xEF\xBB\xBF");
            $header = ['No', 'Full Name', 'Koperasi ID', 'Phone', 'Email', 'Date', 'Time', 'Method'];
            foreach ($custom as $f) {
                $header[] = $f['label'];
            }
            fputcsv($out, $header);
            $i = 1;
            foreach ($rows as $r) {
                $line = [
                    $i++, $r->name, $r->koperasi_id, $r->phone_number,
                    optional($r->shareholder)->email, $r->date, $r->time, $r->method,
                ];
                foreach ($custom as $f) {
                    $line[] = $r->custom_data[$f['key']] ?? '';
                }
                fputcsv($out, $line);
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function clear()
    {
        $m = Meeting::current();

        $count = Attendance::where('meeting_id', $m->id)->delete();

        // Wipe ALL captured contact details so no stale (e.g. dummy/test) phone or
        // email can prefill the check-in forms. Names + Nombor Ahli stay, and the
        // uploaded roster (memberships) is never touched. Raw update skips timestamps.
        DB::table('shareholders')->update(['phone_number' => '', 'email' => null]);

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

    // Delete a single submission (human error). Removes the check-in record;
    // the shareholder/roster entry is kept.
    public function deleteAttendance($id)
    {
        $att = Attendance::find($id);
        if (!$att) {
            return response()->json(['status' => 'error', 'message' => 'Record not found.'], 404);
        }
        $att->delete();
        return response()->json(['status' => 'success', 'message' => 'Submission deleted.']);
    }

    // Dedicated upload page (linked from the dashboard top bar).
    public function uploadPage()
    {
        return view('admin.upload');
    }

    // The full roster with attendance status for the current meeting. Combines
    // the uploaded membership list with anyone who attended but isn't on it.
    public function roster()
    {
        $m = Meeting::current();

        $attended = Attendance::where('meeting_id', $m->id)
            ->get(['id', 'koperasi_id', 'name', 'time', 'method'])
            ->keyBy('koperasi_id');

        // Known contact details (for prefilling the manual check-in form).
        $contacts = Shareholder::get(['koperasi_id', 'email', 'phone_number'])->keyBy('koperasi_id');

        $rows = [];
        foreach (Membership::orderBy('name')->get(['name', 'member_id']) as $member) {
            $a = $attended->get($member->member_id);
            $c = $contacts->get($member->member_id);
            $rows[] = [
                'member_id' => $member->member_id,
                'name'      => $a->name ?? $member->name,
                'submitted' => (bool) $a,
                'time'      => $a->time ?? null,
                'method'    => $a->method ?? null,
                'in_roster' => true,
                'email'     => $c->email ?? null,
                'phone'     => $c->phone_number ?? null,
            ];
            if ($a) {
                $attended->forget($member->member_id);
            }
        }

        // Walk-ins / ad-hoc IDs that attended but aren't on the roster.
        foreach ($attended as $a) {
            $c = $contacts->get($a->koperasi_id);
            $rows[] = [
                'member_id' => $a->koperasi_id,
                'name'      => $a->name,
                'submitted' => true,
                'time'      => $a->time,
                'method'    => $a->method,
                'in_roster' => false,
                'email'     => $c->email ?? null,
                'phone'     => $c->phone_number ?? null,
            ];
        }

        return response()->json(['status' => 'success', 'data' => $rows]);
    }

    // Mark a member present (attend=true) or remove their check-in (attend=false)
    // for the current meeting. Used by the Quick Check-In list and the Verify page.
    public function markAttendance(Request $request)
    {
        $data = $request->validate([
            'member_id'    => ['required', 'string', 'max:100'],
            'attend'       => ['required', 'boolean'],
            'name'         => ['nullable', 'string', 'max:150'],
            'email'        => ['nullable', 'email', 'max:150'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'custom'       => ['array'],
        ]);

        $m = Meeting::current();
        $memberId = trim($data['member_id']);

        if (!$data['attend']) {
            Attendance::where('meeting_id', $m->id)->where('koperasi_id', $memberId)->delete();
            return response()->json(['status' => 'success', 'message' => 'Marked as not attended.']);
        }

        // Required fields follow the same "Check-in form fields" settings as the public form.
        $cfg   = $m->formConfig();
        $email = trim((string) ($data['email'] ?? ''));
        $phone = trim((string) ($data['phone_number'] ?? ''));
        $missing = [];
        if ($cfg['phone_required'] && $phone === '') $missing[] = 'phone number';
        if ($cfg['email_required'] && $email === '') $missing[] = 'email';
        if ($missing) {
            return response()->json(['status' => 'error', 'message' => 'Please provide the ' . implode(' and ', $missing) . ' to check this member in.'], 422);
        }

        // Custom columns are collected if filled (not hard-required on the staff path).
        $customData = [];
        foreach ($cfg['custom'] as $f) {
            $v = $request->input('custom.' . $f['key']);
            if ($v !== null && trim((string) $v) !== '') {
                $customData[$f['key']] = $v;
            }
        }

        if (Attendance::where('meeting_id', $m->id)->where('koperasi_id', $memberId)->exists()) {
            return response()->json(['status' => 'success', 'message' => 'Already checked in.']);
        }

        $membership = Membership::where('member_id', $memberId)->first();
        $name = $membership->name ?? ($data['name'] ?? $memberId);

        DB::transaction(function () use ($m, $memberId, $name, $email, $phone, $customData) {
            $shareholder = Shareholder::updateOrCreate(
                ['koperasi_id' => $memberId],
                ['name' => $name, 'phone_number' => $phone, 'email' => $email ?: null]
            );
            Attendance::create([
                'meeting_id'         => $m->id,
                'shareholder_id'     => $shareholder->id,
                'koperasi_id'        => $memberId,
                'name'               => $name,
                'phone_number'       => $phone,
                'date'               => now()->format('Y-m-d'),
                'time'               => now()->format('H:i:s'),
                'device_fingerprint' => 'manual:' . $memberId,
                'status'             => 'present',
                'method'             => 'manual',
                'custom_data'        => $customData ?: null,
            ]);
        });

        return response()->json(['status' => 'success', 'message' => 'Marked as attended.']);
    }
}
