<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournamentgroupteams', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id', true);
            $table->integer('group_id')->unsigned();
            $table->integer('team_id')->unsigned();
            $table->integer('position_final')->unsigned()->nullable();
            $table->integer('points')->default(0);
            $table->integer('goal_diff')->default(0);
            $table->integer('goals_for')->default(0);
            $table->integer('goals_against')->default(0);
            $table->integer('matches_played')->default(0);
            $table->integer('wins')->default(0);
            $table->integer('draws')->default(0);
            $table->integer('losses')->default(0);

            $table->foreign('group_id')->references('group_id')->on('tournamentgroups')->onDelete('cascade');
            $table->foreign('team_id')->references('team_id')->on('teams');

            $table->unique(['group_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournamentgroupteams');
    }
};
