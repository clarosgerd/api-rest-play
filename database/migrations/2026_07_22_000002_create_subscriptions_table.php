<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('subscription_id');
            $table->unsignedInteger('user_id');
            $table->string('stripe_subscription_id')->nullable();
            $table->string('stripe_customer_id')->nullable();
            $table->enum('plan', ['pro', 'premium']);
            $table->enum('status', ['active', 'cancelled', 'past_due', 'trialing', 'incomplete'])->default('incomplete');
            $table->decimal('monthly_price', 8, 2);
            $table->decimal('annual_price', 8, 2)->nullable();
            $table->boolean('is_annual')->default(false);
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
