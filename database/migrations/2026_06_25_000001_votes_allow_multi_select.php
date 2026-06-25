<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Board election now lets each voter pick EXACTLY N candidates (N = vote_seats),
    // stored as N rows per voter. The old unique(meeting_id, voter_koperasi_id) only
    // allowed one row per voter, so it must become unique per (voter, candidate):
    // a voter may pick up to one row per candidate, but can't double-vote a candidate.
    public function up()
    {
        Schema::table('votes', function (Blueprint $table) {
            // Drop the old "one row per voter" unique key (named by its columns).
            try { $table->dropUnique(['meeting_id', 'voter_koperasi_id']); } catch (\Throwable $e) {}
        });

        // Guard against a re-run leaving the new key behind.
        Schema::table('votes', function (Blueprint $table) {
            try { $table->unique(['meeting_id', 'voter_koperasi_id', 'candidate_id']); } catch (\Throwable $e) {}
        });
    }

    public function down()
    {
        Schema::table('votes', function (Blueprint $table) {
            try { $table->dropUnique(['meeting_id', 'voter_koperasi_id', 'candidate_id']); } catch (\Throwable $e) {}
            try { $table->unique(['meeting_id', 'voter_koperasi_id']); } catch (\Throwable $e) {}
        });
    }
};
