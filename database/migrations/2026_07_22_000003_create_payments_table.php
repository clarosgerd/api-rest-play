<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('payment_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('subscription_id')->nullable();
            $table->string('stripe_payment_id')->nullable();
            $table->string('stripe_invoice_id')->nullable();
            $table->decimal('amount', 8, 2);
            $table->string('currency', 3)->default('USD');
            $table->enum('status', ['succeeded', 'pending', 'failed', 'refunded'])->default('pending');
            $table->string('description')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('subscription_id')->references('subscription_id')->on('subscriptions')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
