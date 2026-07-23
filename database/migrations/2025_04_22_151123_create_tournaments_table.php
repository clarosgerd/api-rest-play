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
        Schema::create('tournaments', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('tournament_id', true);
            $table->string('name');
            $table->string('season');
            $table->string('country');
            $table->string('logo_url')->nullable();
            $table->string('flag_url')->nullable();
            $table->string('country_code');
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->boolean('active')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};
