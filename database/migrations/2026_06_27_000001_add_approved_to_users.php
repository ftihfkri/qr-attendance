<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Default true so every EXISTING account (admin + already-created staff)
        // and every admin-created account stays usable. Only public self-
        // registration sets this to false, so those need admin approval to log in.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('approved')->default(true)->after('role');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('approved');
        });
    }
};
