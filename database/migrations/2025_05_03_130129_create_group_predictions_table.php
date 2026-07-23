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
        Schema::create('grouppredictions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('group_prediction_id', true);
            $table->integer('group_id')->unsigned();
            $table->integer('prediction_id')->unsigned();
            $table->integer('points_awarded');
        });
        Schema::table('grouppredictions', function ($table) {
            $table->foreign('group_id')->references('group_id')->on('groups');
            $table->foreign('prediction_id')->references('prediction_id')->on('predictions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grouppredictions');
    }
};
