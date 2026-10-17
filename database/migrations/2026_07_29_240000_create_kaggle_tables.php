<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kaggle_results', function (Blueprint $table) {
            $table->id('result_id');
            $table->date('match_date');
            $table->string('home_team', 100);
            $table->string('away_team', 100);
            $table->unsignedTinyInteger('home_score');
            $table->unsignedTinyInteger('away_score');
            $table->string('tournament', 150);
            $table->string('city', 100);
            $table->string('country', 100);
            $table->boolean('neutral');
            $table->timestamps();

            $table->index(['home_team', 'away_team', 'match_date']);
            $table->index('tournament');
        });

        Schema::create('kaggle_goalscorers', function (Blueprint $table) {
            $table->id('scorer_id');
            $table->date('match_date');
            $table->string('home_team', 100);
            $table->string('away_team', 100);
            $table->string('team', 100);
            $table->string('scorer', 150);
            $table->string('minute', 10)->nullable();
            $table->boolean('own_goal')->default(false);
            $table->boolean('penalty')->default(false);
            $table->timestamps();

            $table->index(['home_team', 'away_team', 'match_date']);
        });

        Schema::create('kaggle_shootouts', function (Blueprint $table) {
            $table->id('shootout_id');
            $table->date('match_date');
            $table->string('home_team', 100);
            $table->string('away_team', 100);
            $table->string('winner', 100);
            $table->string('first_shooter', 100)->nullable();
            $table->timestamps();

            $table->index(['home_team', 'away_team', 'match_date']);
        });

        Schema::create('kaggle_former_names', function (Blueprint $table) {
            $table->id('name_id');
            $table->string('former', 100);
            $table->string('current', 100);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
        });

        Schema::create('kaggle_elo_ratings', function (Blueprint $table) {
            $table->id('elo_id');
            $table->string('team_name', 100);
            $table->integer('elo_rating')->default(1500);
            $table->integer('matches_played')->default(0);
            $table->date('last_match_date')->nullable();
            $table->date('elo_as_of_date')->nullable();
            $table->timestamps();

            $table->unique('team_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kaggle_elo_ratings');
        Schema::dropIfExists('kaggle_former_names');
        Schema::dropIfExists('kaggle_shootouts');
        Schema::dropIfExists('kaggle_goalscorers');
        Schema::dropIfExists('kaggle_results');
    }
};
