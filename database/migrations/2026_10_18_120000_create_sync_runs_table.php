<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_runs', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('sync_run_id');
            $table->string('source');
            $table->string('command');
            $table->json('arguments')->nullable();
            $table->enum('status', ['queued', 'running', 'completed', 'failed'])->default('queued');
            $table->longText('log')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_runs');
    }
};
