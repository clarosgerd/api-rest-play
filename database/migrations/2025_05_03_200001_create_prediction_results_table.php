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
        Schema::create('predictionresults', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('result_id', true);
            $table->integer('prediction_id')->unsigned();
            $table->integer('points_earned');
            $table->dateTime('processed_at');
        });
        Schema::table('predictionresults', function ($table) {
            $table->foreign('prediction_id')->references('prediction_id')->on('predictions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('predictionresults');
    }
};
