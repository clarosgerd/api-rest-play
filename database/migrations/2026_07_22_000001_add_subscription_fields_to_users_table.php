<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('subscription_tier')->default('free')->after('role');
            $table->timestamp('subscription_expires_at')->nullable()->after('subscription_tier');
            $table->string('stripe_customer_id')->nullable()->after('subscription_expires_at');
            $table->string('stripe_trial_ends_at')->nullable()->after('stripe_customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'subscription_tier',
                'subscription_expires_at',
                'stripe_customer_id',
                'stripe_trial_ends_at',
            ]);
        });
    }
};
