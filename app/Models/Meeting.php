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
        'vote_token',
        'voting_open',
        'vote_seats',
        'vote_starts_at',
        'vote_ends_at',
        'form_config',
    ];

    protected $casts = [
        'submission_open' => 'boolean',
        'opens_at'        => 'datetime',
        'closes_at'       => 'datetime',
        'voting_open'     => 'boolean',
        'vote_starts_at'  => 'datetime',
        'vote_ends_at'    => 'datetime',
        'form_config'     => 'array',
    ];

    // Check-in form configuration with sensible defaults. Name + Nombor Ahli are
    // always required (the roster name+ID match depends on them); phone, email and
    // any custom columns are configurable. custom = [{key, label, required}].
    public function formConfig(): array
    {
        $cfg = $this->form_config ?? [];
        return [
            'phone_required' => $cfg['phone_required'] ?? true,
            'email_required' => $cfg['email_required'] ?? true,
            'custom'         => array_values($cfg['custom'] ?? []),
        ];
    }

    public function candidates()
    {
        return $this->hasMany(Candidate::class);
    }

    public function votes()
    {
        return $this->hasMany(Vote::class);
    }

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

    // ──────────────────────────────────────────────────────────────────────
    //  Board Election
    // ──────────────────────────────────────────────────────────────────────

    public static function generateVoteToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    // Voting is open when the master switch is on AND (if a window is set) the
    // current time is inside vote_starts_at..vote_ends_at.
    public function isVotingOpen(): bool
    {
        if (!$this->voting_open) {
            return false;
        }
        $now = now();
        if ($this->vote_starts_at && $now->lt($this->vote_starts_at)) {
            return false;
        }
        if ($this->vote_ends_at && $now->gt($this->vote_ends_at)) {
            return false;
        }
        return true;
    }

    // Voting has been set up and is now over (timer elapsed, or closed manually
    // after at least one vote was cast).
    public function votingFinished(): bool
    {
        if (!$this->vote_token) {
            return false;
        }
        if ($this->vote_ends_at && now()->gt($this->vote_ends_at)) {
            return true;
        }
        return !$this->voting_open && $this->votes()->exists();
    }

    // Live tally: candidates sorted by votes desc, with percentages and — once
    // voting has finished — the top-N (vote_seats) with votes flagged as winners.
    public function tally(): array
    {
        $candidates = $this->candidates()->orderBy('name')->get();
        $counts = $this->votes()
            ->selectRaw('candidate_id, COUNT(*) as c')
            ->groupBy('candidate_id')
            ->pluck('c', 'candidate_id');
        $total = (int) $counts->sum();

        $rows = $candidates->map(fn ($c) => [
            'candidate_id' => $c->id,
            'koperasi_id'  => $c->koperasi_id,
            'name'         => $c->name,
            'votes'        => (int) ($counts[$c->id] ?? 0),
        ])->sortByDesc('votes')->values()
          ->map(fn ($r) => $r + ['percent' => $total > 0 ? round($r['votes'] / $total * 100, 1) : 0.0]);

        $finished  = $this->votingFinished();
        $winnerIds = $finished
            ? $rows->where('votes', '>', 0)->take((int) $this->vote_seats)->pluck('candidate_id')->all()
            : [];

        return [
            'total_votes'     => $total,
            'seats'           => (int) $this->vote_seats,
            'voting_active'   => $this->isVotingOpen(),
            'voting_finished' => $finished,
            'candidates'      => $rows->map(fn ($r) => $r + ['is_winner' => in_array($r['candidate_id'], $winnerIds)])->all(),
        ];
    }
}
