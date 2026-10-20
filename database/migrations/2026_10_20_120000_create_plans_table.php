<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Planes administrables desde el panel de super admin (20/10/2026, pedido
 * real del usuario) — reemplaza Subscription::getPlans()/User::getTierLimits()
 * hardcodeados. Las 4 filas base (free/predictor/pro/premium) se insertan acá
 * mismo con los valores que ya estaban hardcodeados, para que ningún usuario
 * suscripto hoy note el cambio — ver DEPLOY-CHECKLIST correspondiente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->increments('id');
            $table->string('slug')->unique();
            $table->string('name');
            $table->decimal('monthly_price_usd', 8, 2)->nullable();
            $table->decimal('annual_price_usd', 8, 2)->nullable();
            $table->decimal('monthly_price_bob', 8, 2)->nullable();
            $table->decimal('annual_price_bob', 8, 2)->nullable();
            $table->string('stripe_price_id_monthly')->nullable();
            $table->string('stripe_price_id_annual')->nullable();
            // -1 = ilimitado, 0 = no puede crear/poseer (mismo código que ya
            // usaba User::getTierLimits() hardcodeado).
            $table->integer('tournament_limit')->default(0);
            $table->integer('members_per_group')->default(0);
            $table->integer('predictions_per_day')->default(0);
            $table->json('external_leagues')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Free: hoy (bug real) podía crear 2 torneos/10 miembros — se corrige
        // a 0/0 porque "crear torneo/grupo propio" pasa a requerir Pro/Premium.
        DB::table('plans')->insert([
            [
                'slug' => 'free', 'name' => 'Free',
                'monthly_price_usd' => null, 'annual_price_usd' => null,
                'monthly_price_bob' => null, 'annual_price_bob' => null,
                'stripe_price_id_monthly' => null, 'stripe_price_id_annual' => null,
                'tournament_limit' => 0, 'members_per_group' => 0, 'predictions_per_day' => 0,
                'external_leagues' => json_encode([]), 'is_active' => true, 'sort_order' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'slug' => 'predictor', 'name' => 'Predictor',
                'monthly_price_usd' => 1.99, 'annual_price_usd' => 14.99,
                'monthly_price_bob' => 14, 'annual_price_bob' => 104,
                'stripe_price_id_monthly' => env('STRIPE_PREDICTOR_MONTHLY_PRICE_ID'),
                'stripe_price_id_annual' => env('STRIPE_PREDICTOR_ANNUAL_PRICE_ID'),
                'tournament_limit' => 0, 'members_per_group' => 0, 'predictions_per_day' => -1,
                'external_leagues' => json_encode([]), 'is_active' => true, 'sort_order' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'slug' => 'pro', 'name' => 'Pro',
                'monthly_price_usd' => 4.99, 'annual_price_usd' => 39.99,
                'monthly_price_bob' => 35, 'annual_price_bob' => 279,
                'stripe_price_id_monthly' => env('STRIPE_PRO_MONTHLY_PRICE_ID'),
                'stripe_price_id_annual' => env('STRIPE_PRO_ANNUAL_PRICE_ID'),
                'tournament_limit' => 5, 'members_per_group' => 50, 'predictions_per_day' => 20,
                'external_leagues' => json_encode(['conmebol', 'sudamericana', 'south_america']),
                'is_active' => true, 'sort_order' => 2,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'slug' => 'premium', 'name' => 'Premium',
                'monthly_price_usd' => 9.99, 'annual_price_usd' => 79.99,
                'monthly_price_bob' => 69, 'annual_price_bob' => 559,
                'stripe_price_id_monthly' => env('STRIPE_PREMIUM_MONTHLY_PRICE_ID'),
                'stripe_price_id_annual' => env('STRIPE_PREMIUM_ANNUAL_PRICE_ID'),
                'tournament_limit' => -1, 'members_per_group' => -1, 'predictions_per_day' => -1,
                'external_leagues' => json_encode(['all']), 'is_active' => true, 'sort_order' => 3,
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
