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
        Schema::table('subscriptions', function (Blueprint $table) {
            // 'predictor' nunca se agregó al enum de `plan` cuando se creó
            // ese plan (16/10/2026) — crear una Subscription con
            // plan='predictor' fallaba a nivel de BD. 'pending_payment' es
            // el estado nuevo para el flujo de SIP (QR generado, esperando
            // confirmación). ->change() reescribe el enum nativo en
            // MySQL/SQLite sin necesitar doctrine/dbal (Laravel 11+).
            $table->enum('plan', ['predictor', 'pro', 'premium'])->change();
            $table->enum('status', ['active', 'cancelled', 'past_due', 'trialing', 'incomplete', 'pending_payment'])
                ->default('incomplete')->change();

            $table->string('payment_provider')->default('stripe')->after('stripe_customer_id');
            $table->string('sip_referencia')->nullable()->unique()->after('payment_provider');
            $table->string('sip_qr_id')->nullable()->after('sip_referencia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['payment_provider', 'sip_referencia', 'sip_qr_id']);
            $table->enum('status', ['active', 'cancelled', 'past_due', 'trialing', 'incomplete'])
                ->default('incomplete')->change();
            $table->enum('plan', ['pro', 'premium'])->change();
        });
    }
};
