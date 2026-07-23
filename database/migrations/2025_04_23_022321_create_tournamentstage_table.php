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
        Schema::create('TournamentStage', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('stage_id', true);
            $table->integer('tournament_id')->unsigned();
            $table->string('name')->nullable()->default(null);
            $table->string('stage_order')->nullable();
            $table->boolean('is_knockout')->nullable();
        });

        Schema::table('TournamentStage', function ($table) {
            $table->foreign('tournament_id')->references('tournament_id')->on('tournaments')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('TournamentStage');
    }
};
