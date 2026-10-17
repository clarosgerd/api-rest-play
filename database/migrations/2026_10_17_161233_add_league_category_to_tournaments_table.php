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
        Schema::table('tournaments', function (Blueprint $table) {
            // 'conmebol' | 'european' | null (demo/privado, sin fuente externa).
            // Reemplaza los rangos de external_id hardcodeados en
            // SyncController::status() por un campo explícito, poblado por
            // los comandos de sync en el momento de sincronizar.
            $table->string('league_category')->nullable()->after('external_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('league_category');
        });
    }
};
