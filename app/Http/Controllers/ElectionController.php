<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Candidate;
use App\Models\Meeting;
use App\Models\Vote;
use Illuminate\Http\Request;

class ElectionController extends Controller
{
    // The admin Board Election management page.
    public function index()
    {
        return view('admin.election', ['meeting' => Meeting::current()]);
    }

    // Serialise the current meeting's voting state for the admin UI.
    private function meetingJson(Meeting $m): array
    {
        return [
            'vote_token'      => $m->vote_token,
            'voting_open'     => (bool) $m->voting_open,
            'voting_active'   => $m->isVotingOpen(),
            'voting_finished' => $m->votingFinished(),
            'vote_seats'      => (int) $m->vote_seats,
            'vote_starts_at'  => optional($m->vote_starts_at)->format('Y-m-d\TH:i'),
            'vote_ends_at'    => optional($m->vote_ends_at)->format('Y-m-d\TH:i'),
        ];
    }

    // Number of attendees eligible to vote = checked-in attendees who are NOT candidates.
    private function eligibleCount(Meeting $m): int
    {
        $candidateIds = $m->candidates()->pluck('koperasi_id');
        return Attendance::where('meeting_id', $m->id)
            ->whereNotIn('koperasi_id', $candidateIds)
            ->distinct('koperasi_id')
            ->count('koperasi_id');
    }

    // Candidate list + meeting state (polled by the admin page).
    public function results()
    {
        $m = Meeting::current();
        return response()->json([
            'status'          => 'success',
            'meeting'         => $this->meetingJson($m),
            'candidates'      => $m->candidates()->orderBy('name')->get(['id', 'koperasi_id', 'name'])
                ->map(fn ($c) => ['candidate_id' => $c->id, 'koperasi_id' => $c->koperasi_id, 'name' => $c->name]),
            'tally'           => $m->tally(),
            'eligible_voters' => $this->eligibleCount($m),
        ]);
    }

    // Search checked-in attendees of the current meeting (the candidate pool).
    public function candidateSearch(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $m = Meeting::current();

        $candidateIds = $m->candidates()->pluck('koperasi_id')->all();

        $rows = Attendance::where('meeting_id', $m->id)
            ->when($q !== '', fn ($query) => $query->where(function ($sq) use ($q) {
                $sq->where('name', 'like', "%{$q}%")
                   ->orWhere('koperasi_id', 'like', "%{$q}%");
            }))
            ->orderBy('name')
            ->get(['koperasi_id', 'name'])
            ->unique('koperasi_id')
            ->take(50)
            ->map(fn ($a) => [
                'koperasi_id'  => $a->koperasi_id,
                'name'         => $a->name,
                'is_candidate' => in_array($a->koperasi_id, $candidateIds, true),
            ])->values();

        return response()->json(['status' => 'success', 'data' => $rows]);
    }

    // Add a candidate — must be a checked-in attendee of this meeting.
    public function addCandidate(Request $request)
    {
        $data = $request->validate([
            'koperasi_id' => ['required', 'string', 'max:100'],
        ]);

        $m = Meeting::current();
        $attendee = Attendance::where('meeting_id', $m->id)
            ->where('koperasi_id', $data['koperasi_id'])
            ->first();

        if (!$attendee) {
            return response()->json(['status' => 'error', 'message' => 'Only members who have checked in can be nominated.'], 422);
        }

        Candidate::firstOrCreate(
            ['meeting_id' => $m->id, 'koperasi_id' => $data['koperasi_id']],
            ['name' => $attendee->name]
        );

        return response()->json(['status' => 'success', 'message' => 'Candidate added.']);
    }

    public function removeCandidate($id)
    {
        $m = Meeting::current();
        $candidate = Candidate::where('meeting_id', $m->id)->where('id', $id)->first();
        if (!$candidate) {
            return response()->json(['status' => 'error', 'message' => 'Candidate not found.'], 404);
        }
        // No DB-level cascade — remove this candidate's votes explicitly.
        Vote::where('candidate_id', $candidate->id)->delete();
        $candidate->delete();
        return response()->json(['status' => 'success', 'message' => 'Candidate removed.']);
    }

    // Open / close voting, or update seats / timer.
    public function setVoting(Request $request)
    {
        $data = $request->validate([
            'action'       => ['required', 'in:open,close,update'],
            'vote_seats'   => ['nullable', 'integer', 'min:1', 'max:50'],
            'duration_min' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ]);

        $m = Meeting::current();

        if (!$m->vote_token) {
            $m->vote_token = Meeting::generateVoteToken();
        }
        if (!empty($data['vote_seats'])) {
            $m->vote_seats = $data['vote_seats'];
        }

        if ($data['action'] === 'open') {
            if ($m->candidates()->count() < 1) {
                return response()->json(['status' => 'error', 'message' => 'Add at least one candidate before opening voting.'], 422);
            }
            $m->voting_open    = true;
            $m->vote_starts_at = now();
            $m->vote_ends_at   = !empty($data['duration_min'])
                ? now()->addMinutes((int) $data['duration_min'])
                : null;
        } elseif ($data['action'] === 'close') {
            $m->voting_open  = false;
            $m->vote_ends_at = $m->vote_ends_at ?? now();
        } else { // update
            if (!empty($data['duration_min'])) {
                $m->vote_ends_at = now()->addMinutes((int) $data['duration_min']);
            }
        }

        $m->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Voting settings saved.',
            'meeting' => $this->meetingJson($m->fresh()),
        ]);
    }
}
