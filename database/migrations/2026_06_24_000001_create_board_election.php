<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Voting controls live on the single rolling meeting (Meeting::current()).
        Schema::table('meetings', function (Blueprint $table) {
            $table->string('vote_token', 64)->nullable();
            $table->boolean('voting_open')->default(false);
            $table->unsignedInteger('vote_seats')->default(1);
            $table->dateTime('vote_starts_at')->nullable();
            $table->dateTime('vote_ends_at')->nullable();
        });

        // Candidates: each must be a checked-in attendee (koperasi_id) of the meeting.
        // No DB-level FK (the legacy meetings.id is a signed int, which MySQL refuses
        // to reference from a bigint) — relationships are enforced in application code.
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meeting_id')->index();
            $table->string('koperasi_id', 100);
            $table->string('name', 150);
            $table->timestamps();
            $table->unique(['meeting_id', 'koperasi_id']);
        });

        // Votes: one per voter (koperasi_id) per meeting, enforced by the unique key.
        Schema::create('votes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meeting_id')->index();
            $table->unsignedBigInteger('candidate_id')->index();
            $table->string('voter_koperasi_id', 100);
            $table->string('voter_name', 150)->nullable();
            $table->string('device_fingerprint', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->unique(['meeting_id', 'voter_koperasi_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('votes');
        Schema::dropIfExists('candidates');
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn(['vote_token', 'voting_open', 'vote_seats', 'vote_starts_at', 'vote_ends_at']);
        });
    }
};
