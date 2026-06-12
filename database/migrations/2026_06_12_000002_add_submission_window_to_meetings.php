<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->boolean('submission_open')->default(true)->after('is_active');
            $table->dateTime('opens_at')->nullable()->after('submission_open');
            $table->dateTime('closes_at')->nullable()->after('opens_at');
        });
    }

    public function down()
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn(['submission_open', 'opens_at', 'closes_at']);
        });
    }
};
