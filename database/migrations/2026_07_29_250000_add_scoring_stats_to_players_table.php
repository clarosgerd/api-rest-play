<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropUnique('players_external_id_unique');
        });

        Schema::table('players', function (Blueprint $table) {
            $table->integer('goals')->default(0);
            $table->integer('assists')->default(0);
            $table->integer('games_played')->default(0);
            $table->integer('games_minutes')->default(0);
            $table->unsignedInteger('tournament_id')->nullable();
            $table->string('season', 10)->nullable();

            $table->unique(['external_id', 'tournament_id', 'season'], 'players_external_tournament_season_unique');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropUnique('players_external_tournament_season_unique');
            $table->dropColumn(['goals', 'assists', 'games_played', 'games_minutes', 'tournament_id', 'season']);
        });

        Schema::table('players', function (Blueprint $table) {
            $table->unique('external_id');
        });
    }
};
