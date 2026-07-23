<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropForeign(['home_team_id']);
            $table->dropForeign(['away_team_id']);
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->integer('home_team_id')->unsigned()->nullable()->change();
            $table->integer('away_team_id')->unsigned()->nullable()->change();
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->foreign('home_team_id')->references('team_id')->on('teams')->nullOnDelete();
            $table->foreign('away_team_id')->references('team_id')->on('teams')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropForeign(['home_team_id']);
            $table->dropForeign(['away_team_id']);
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->integer('home_team_id')->unsigned()->nullable(false)->change();
            $table->integer('away_team_id')->unsigned()->nullable(false)->change();
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->foreign('home_team_id')->references('team_id')->on('teams');
            $table->foreign('away_team_id')->references('team_id')->on('teams');
        });
    }
};
