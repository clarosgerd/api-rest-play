<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->integer('external_id')->nullable()->unique()->after('tournament_id');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->integer('external_id')->nullable()->unique()->after('team_id');
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->integer('external_id')->nullable()->unique()->after('match_id');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('external_id');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('external_id');
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('external_id');
        });
    }
};
