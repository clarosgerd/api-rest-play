<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_elo_ratings', function (Blueprint $table) {
            $table->id('elo_id');
            $table->unsignedInteger('team_id');
            $table->unsignedInteger('tournament_id')->nullable();
            $table->integer('elo_rating')->default(1500);
            $table->integer('matches_played')->default(0);
            $table->timestamp('last_match_date')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'tournament_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_elo_ratings');
    }
};
