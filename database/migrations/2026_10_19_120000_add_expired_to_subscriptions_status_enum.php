<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bug real encontrado al agendar subscription:check-expired (19/10/2026):
     * el comando pone status='expired' pero ese valor nunca estuvo en el
     * enum — update() siempre tiró un error de constraint, nunca se probó
     * porque el comando nunca corrió (ni agendado ni testeado hasta ahora).
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->enum('status', ['active', 'cancelled', 'past_due', 'trialing', 'incomplete', 'pending_payment', 'expired'])
                ->default('incomplete')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->enum('status', ['active', 'cancelled', 'past_due', 'trialing', 'incomplete', 'pending_payment'])
                ->default('incomplete')
                ->change();
        });
    }
};
