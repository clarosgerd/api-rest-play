<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('match_id', true);
            $table->integer('home_team_id')->unsigned();
            $table->integer('away_team_id')->unsigned();
            $table->integer('tournament_id')->unsigned();
            $table->integer('stage_id')->unsigned();
            $table->dateTime('match_date');
            $table->enum('status', ['scheduled', 'live', 'finished', 'canceled'])->default('scheduled'); // Define the ENUM column
            $table->integer('home_score')->default(0);
            $table->integer('away_score')->default(0);
        });
        Schema::table('matches', function ($table) {
            $table->foreign('home_team_id')->references('team_id')->on('teams');
            $table->foreign('away_team_id')->references('team_id')->on('teams');
            $table->foreign('tournament_id')->references('tournament_id')->on('tournaments');
            $table->foreign('stage_id')->references('stage_id')->on('tournamentstage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
