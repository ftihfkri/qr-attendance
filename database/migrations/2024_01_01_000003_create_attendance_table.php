<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meeting_id');
            $table->unsignedBigInteger('shareholder_id');
            $table->string('koperasi_id');
            $table->string('name');
            $table->string('phone_number');
            $table->string('date');
            $table->string('time');
            $table->double('location_lat')->nullable();
            $table->double('location_lng')->nullable();
            $table->string('device_fingerprint');
            $table->string('status')->default('present');
            $table->double('distance_from_venue')->nullable();
            $table->string('method')->default('scanned'); // scanned | manual
            $table->timestamps();

            $table->unique(['meeting_id', 'koperasi_id']);
            $table->unique(['meeting_id', 'device_fingerprint']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('attendance');
    }
};
