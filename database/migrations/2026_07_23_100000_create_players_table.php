<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('player_id');
            $table->unsignedInteger('team_id')->nullable();
            $table->string('name');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('position')->nullable();
            $table->string('nationality')->nullable();
            $table->date('birth_date')->nullable();
            $table->integer('age')->nullable();
            $table->string('photo_url')->nullable();
            $table->integer('external_id')->nullable()->unique();

            $table->foreign('team_id')->references('team_id')->on('teams')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
