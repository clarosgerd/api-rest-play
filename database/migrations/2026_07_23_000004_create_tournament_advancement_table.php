<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournamentadvancement', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('advancement_id', true);
            $table->integer('rule_id')->unsigned();
            $table->integer('team_id')->unsigned();
            $table->integer('source_group_id')->unsigned()->nullable();
            $table->integer('source_position')->unsigned()->nullable();
            $table->boolean('confirmed')->default(false);
            $table->timestamp('confirmed_at')->nullable();

            $table->foreign('rule_id')->references('rule_id')->on('tournamentadvancementrules')->onDelete('cascade');
            $table->foreign('team_id')->references('team_id')->on('teams');
            $table->foreign('source_group_id')->references('group_id')->on('tournamentgroups');

            $table->unique(['rule_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournamentadvancement');
    }
};
