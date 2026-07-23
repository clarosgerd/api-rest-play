<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grouppool', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('pool_id');
            $table->integer('group_id')->unsigned();
            $table->integer('user_id')->unsigned();
            $table->integer('match_id')->unsigned()->nullable();
            $table->decimal('amount_applied', 10, 2);
            $table->integer('points_earned')->default(0);
            $table->decimal('prize_won', 10, 2)->default(0);
            $table->enum('status', ['pending', 'scored', 'distributed'])->default('pending');
            $table->timestamp('scored_at')->nullable();
            $table->timestamp('distributed_at')->nullable();

            $table->foreign('group_id')->references('group_id')->on('groups')->onDelete('cascade');
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('match_id')->references('match_id')->on('matches')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grouppool');
    }
};
