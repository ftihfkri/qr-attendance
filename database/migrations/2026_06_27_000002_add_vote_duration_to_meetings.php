<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Persist the chosen timer (minutes) so "Save" can store it without
        // opening voting. It's only turned into an end-time when voting opens.
        Schema::table('meetings', function (Blueprint $table) {
            $table->unsignedInteger('vote_duration_min')->nullable()->after('vote_ends_at');
        });
    }

    public function down()
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn('vote_duration_min');
        });
    }
};
