<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournamentgroups', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('group_id', true);
            $table->integer('stage_id')->unsigned();
            $table->string('name', 50);
            $table->integer('group_order')->default(1);
            $table->string('status', 20)->default('pending'); // pending, approved, rejected
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('stage_id')->references('stage_id')->on('tournamentstage')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournamentgroups');
    }
};
