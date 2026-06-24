<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Per-meeting check-in form configuration: which fields are required and
        // any custom columns the admin/staff added.
        Schema::table('meetings', function (Blueprint $table) {
            $table->json('form_config')->nullable();
        });

        // Values entered for the custom columns at check-in time.
        Schema::table('attendance', function (Blueprint $table) {
            $table->json('custom_data')->nullable();
        });
    }

    public function down()
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn('form_config');
        });
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropColumn('custom_data');
        });
    }
};
