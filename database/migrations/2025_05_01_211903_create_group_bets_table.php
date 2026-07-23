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
        Schema::create('groupbets', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('group_bet_id', true);
            $table->integer('group_id')->unsigned();
            $table->integer('bet_id')->unsigned();
            $table->integer('created_by')->unsigned();
            $table->dateTime('created_at');
        });
        Schema::table('groupbets', function ($table) {
            $table->foreign('bet_id')->references('bet_id')->on('bets')->onDelete('cascade');
            $table->foreign('group_id')->references('group_id')->on('groups')->onDelete('cascade');
            $table->foreign('created_by')->references('user_id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('groupbets');
    }
};
