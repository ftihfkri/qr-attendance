<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Allow several members to check in from one shared phone (common with elderly
    // attendees). One check-in per MEMBER is still enforced by the koperasi_id key.
    public function up()
    {
        $exists = collect(DB::select(
            "SHOW INDEX FROM attendance WHERE Key_name = 'attendance_meeting_id_device_fingerprint_key'"
        ))->isNotEmpty();

        if ($exists) {
            Schema::table('attendance', function (Blueprint $table) {
                $table->dropUnique('attendance_meeting_id_device_fingerprint_key');
            });
        }
    }

    public function down()
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->unique(['meeting_id', 'device_fingerprint'], 'attendance_meeting_id_device_fingerprint_key');
        });
    }
};
