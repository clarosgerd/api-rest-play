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
        Schema::create('odds', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('odd_id', true);
            $table->integer('match_id')->unsigned();
            $table->enum('bet_type', ['1X2', 'over_under', 'handicap', 'both_teams_score', 'correct_score'])->default('1X2'); // Define the ENUM column
            $table->String('option');
            $table->decimal('value');
            $table->dateTime('last_update');
        });
        Schema::table('odds', function ($table) {
            $table->foreign('match_id')->references('match_id')->on('matches');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('odds');
    }
};
