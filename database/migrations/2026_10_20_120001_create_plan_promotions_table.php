<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promocionales por plan y ventana de fechas (20/10/2026) — mismo patrón que
 * Tournament::is_promotional/promotional_ends_at, pero como tabla aparte
 * porque un plan puede tener varias promos a lo largo del tiempo (Tournament
 * solo tiene 1 slot).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_promotions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('plan_id');
            $table->string('name');
            // Overrides nullable — si no vienen, no pisan el valor base del plan.
            $table->decimal('monthly_price_usd', 8, 2)->nullable();
            $table->decimal('annual_price_usd', 8, 2)->nullable();
            $table->decimal('monthly_price_bob', 8, 2)->nullable();
            $table->decimal('annual_price_bob', 8, 2)->nullable();
            $table->integer('predictions_per_day')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('plan_id')->references('id')->on('plans')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_promotions');
    }
};
