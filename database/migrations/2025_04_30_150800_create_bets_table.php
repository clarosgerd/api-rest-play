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
        Schema::create('bets', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('bet_id', true);
            $table->integer('user_id')->unsigned();
            $table->integer('match_id')->unsigned();
            $table->integer('odd_id')->unsigned();
            $table->decimal('amount');
            $table->decimal('potential_payout');
            $table->enum('status', ['pending', 'won', 'lost', 'refunded'])->default('pending'); // Define the ENUM column;
            $table->dateTime('placed_at');
        });
        Schema::table('bets', function ($table) {
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('match_id')->references('match_id')->on('matches')->onDelete('cascade');
            $table->foreign('odd_id')->references('odd_id')->on('odds')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bets');
    }
};
