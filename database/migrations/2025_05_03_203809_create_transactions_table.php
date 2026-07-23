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

        Schema::create('transactions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('transaction_id', true);
            $table->integer('user_id')->unsigned();
            $table->enum('type', ['deposit', 'withdrawal', 'bet', 'win', 'bonus', 'refund'])->default('withdrawal'); // Define the ENUM column; ;
            $table->decimal('amount');
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending'); // Define the ENUM column; ;
            $table->String('reference')->nullable();
            $table->dateTime('processed_at');
        });
        Schema::table('transactions', function ($table) {
            $table->foreign('user_id')->references('user_id')->on('Users');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
