<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Candidate;
use App\Models\Meeting;
use App\Models\Vote;
use Illuminate\Http\Request;

class VotingController extends Controller
{
    private function meeting(string $token): ?Meeting
    {
        return Meeting::where('vote_token', $token)->first();
    }

    // Decide whether a koperasi_id may cast a vote in this meeting.
    // Returns [status, message] where status ∈ ok|closed|not_attendee|is_candidate|already_voted
    private function eligibility(Meeting $m, string $koperasiId): array
    {
        if (!$m->isVotingOpen()) {
            return ['closed', 'Voting is not open at the moment.'];
        }
        $attended = Attendance::where('meeting_id', $m->id)->where('koperasi_id', $koperasiId)->exists();
        if (!$attended) {
            return ['not_attendee', 'This Nombor Ahli has not checked in to the meeting, so it cannot vote.'];
        }
        $isCandidate = Candidate::where('meeting_id', $m->id)->where('koperasi_id', $koperasiId)->exists();
        if ($isCandidate) {
            return ['is_candidate', 'Candidates cannot vote.'];
        }
        $voted = Vote::where('meeting_id', $m->id)->where('voter_koperasi_id', $koperasiId)->exists();
        if ($voted) {
            return ['already_voted', 'This Nombor Ahli has already voted.'];
        }
        return ['ok', ''];
    }

    // GET /vote/{token} — the public ballot, or projector display mode.
    public function show(Request $request, string $token)
    {
        $meeting = $this->meeting($token);
        if (!$meeting) {
            abort(404, 'This voting link is not valid.');
        }

        $candidates = $meeting->candidates()->orderBy('name')->get(['id', 'name'])
            ->map(fn ($c) => ['candidate_id' => $c->id, 'name' => $c->name]);

        return view('vote', [
            'meeting'    => $meeting,
            'token'      => $token,
            'candidates' => $candidates,
            'display'    => $request->boolean('display'),
        ]);
    }

    // POST /vote/{token} — cast a vote. JSON.
    public function vote(Request $request, string $token)
    {
        $meeting = $this->meeting($token);
        if (!$meeting) {
            return response()->json(['status' => 'error', 'message' => 'This voting link is not valid.'], 404);
        }

        $data = $request->validate([
            'koperasi_id'        => ['required', 'string', 'max:100'],
            'candidate_id'       => ['required', 'integer'],
            'device_fingerprint' => ['nullable', 'string', 'max:255'],
        ]);

        $koperasiId = trim($data['koperasi_id']);

        [$status, $msg] = $this->eligibility($meeting, $koperasiId);
        if ($status !== 'ok') {
            return response()->json(['status' => 'error', 'reason' => $status, 'message' => $msg], 422);
        }

        $candidate = Candidate::where('meeting_id', $meeting->id)->where('id', $data['candidate_id'])->first();
        if (!$candidate) {
            return response()->json(['status' => 'error', 'message' => 'Invalid candidate.'], 422);
        }

        $voterName = Attendance::where('meeting_id', $meeting->id)
            ->where('koperasi_id', $koperasiId)->value('name');

        try {
            Vote::create([
                'meeting_id'         => $meeting->id,
                'candidate_id'       => $candidate->id,
                'voter_koperasi_id'  => $koperasiId,
                'voter_name'         => $voterName,
                'device_fingerprint' => $data['device_fingerprint'] ?? null,
                'ip_address'         => $request->ip(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Race against the unique(meeting_id, voter_koperasi_id) constraint.
            return response()->json(['status' => 'error', 'reason' => 'already_voted', 'message' => 'This Nombor Ahli has already voted.'], 422);
        }

        return response()->json(['status' => 'success', 'message' => 'Your vote has been recorded. Thank you!']);
    }

    // GET /vote/{token}/voters — name autocomplete over this meeting's checked-in
    // attendees (so a voter can type their name and have their Nombor Ahli filled in).
    public function voterSearch(Request $request, string $token)
    {
        $meeting = $this->meeting($token);
        if (!$meeting) {
            return response()->json(['data' => []]);
        }
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['data' => []]);
        }

        $rows = Attendance::where('meeting_id', $meeting->id)
            ->where(function ($sq) use ($q) {
                $sq->where('name', 'like', "%{$q}%")
                   ->orWhere('koperasi_id', 'like', "%{$q}%");
            })
            ->orderBy('name')
            ->limit(8)
            ->get(['name', 'koperasi_id'])
            ->unique('koperasi_id')
            ->values()
            ->map(fn ($a) => ['name' => $a->name, 'member_id' => $a->koperasi_id]);

        return response()->json(['data' => $rows]);
    }

    // GET /vote/{token}/results — live tally for polling (public).
    public function results(string $token)
    {
        $meeting = $this->meeting($token);
        if (!$meeting) {
            return response()->json(['status' => 'error'], 404);
        }

        $candidateIds = $meeting->candidates()->pluck('koperasi_id');
        $eligible = Attendance::where('meeting_id', $meeting->id)
            ->whereNotIn('koperasi_id', $candidateIds)
            ->distinct('koperasi_id')
            ->count('koperasi_id');

        return response()->json([
            'status'          => 'success',
            'title'           => $meeting->title,
            'tally'           => $meeting->tally(),
            'eligible_voters' => $eligible,
        ]);
    }
}
