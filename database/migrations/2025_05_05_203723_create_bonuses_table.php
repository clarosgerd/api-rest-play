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
        Schema::create('bonuses', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('bonus_id', true);
            $table->integer('user_id')->unsigned();
            $table->enum('type', ['welcome', 'deposit', 'loyalty', 'promo'])->default('welcome'); // Define the ENUM column; ;
            $table->decimal('amount')->nullable();
            $table->dateTime('expires_at');
            $table->Boolean('is_used')->default('1');
        });
        Schema::table('bonuses', function ($table) {
            $table->foreign('user_id')->references('user_id')->on('Users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bonuses');
    }
};
