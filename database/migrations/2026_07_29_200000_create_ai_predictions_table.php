<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_predictions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('ai_prediction_id');
            $table->integer('match_id')->unsigned();
            $table->enum('predicted_result', ['home_win', 'away_win', 'draw']);
            $table->tinyInteger('predicted_home_score')->unsigned()->default(0);
            $table->tinyInteger('predicted_away_score')->unsigned()->default(0);
            $table->decimal('home_win_prob', 5, 4)->default(0);
            $table->decimal('draw_prob', 5, 4)->default(0);
            $table->decimal('away_win_prob', 5, 4)->default(0);
            $table->decimal('confidence', 4, 3)->default(0);
            $table->boolean('was_correct')->nullable();
            $table->string('model_version', 20)->default('1.0');
            $table->timestamps();

            $table->foreign('match_id')->references('match_id')->on('matches');
            $table->unique('match_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_predictions');
    }
};
