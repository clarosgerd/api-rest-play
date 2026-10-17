<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->timestamp('last_synced_at')->nullable()->after('external_id');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->timestamp('last_synced_at')->nullable()->after('external_id');
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->timestamp('last_synced_at')->nullable()->after('external_id');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('last_synced_at');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('last_synced_at');
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('last_synced_at');
        });
    }
};
