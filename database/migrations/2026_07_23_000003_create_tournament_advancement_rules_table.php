<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournamentadvancementrules', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('rule_id', true);
            $table->integer('stage_id')->unsigned();
            $table->integer('next_stage_id')->unsigned();
            $table->string('rule_type', 50); // top_n, best_third, manual
            $table->integer('rule_value')->unsigned()->nullable(); // ej: 2 para top 2
            $table->string('description', 255)->nullable();

            $table->foreign('stage_id')->references('stage_id')->on('tournamentstage')->onDelete('cascade');
            $table->foreign('next_stage_id')->references('stage_id')->on('tournamentstage');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournamentadvancementrules');
    }
};
