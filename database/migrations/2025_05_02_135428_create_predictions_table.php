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
        Schema::create('predictions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('prediction_id', true);
            $table->integer('user_id')->unsigned();
            $table->integer('match_id')->unsigned();
            $table->integer('predicted_home_score')->nullable();
            $table->integer('predicted_away_score')->nullable();
            $table->enum('predicted_result', ['home_win', 'away_win', 'draw'])->default('draw'); // Define the ENUM column;
            $table->enum('bet_type', ['exact_score', 'match_result', 'both_teams_score'])->default('exact_score'); // Define the ENUM column;
            $table->tinyInteger('confidence');
            $table->dateTime('created_at');
            $table->boolean('is_public');
        });
        Schema::table('predictions', function ($table) {
            $table->foreign('user_id')->references('user_id')->on('users');
            $table->foreign('match_id')->references('match_id')->on('matches');
        });
        // DB::statement('ALTER TABLE Predictions ADD CONSTRAINT chk_prediction CHECK ((predicted_home_score IS NOT NULL AND predicted_away_score IS NOT NULL) OR  predicted_result IS NOT NULL)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('predictions');
    }
};
