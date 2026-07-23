<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('predictionresults', function (Blueprint $table) {
            $table->json('points_breakdown')->nullable()->after('points_earned');
        });
    }

    public function down(): void
    {
        Schema::table('predictionresults', function (Blueprint $table) {
            $table->dropColumn('points_breakdown');
        });
    }
};
