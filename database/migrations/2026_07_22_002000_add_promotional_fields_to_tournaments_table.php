<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->boolean('is_promotional')->default(false)->after('active');
            $table->integer('promotional_tier_required')->default(2)->after('is_promotional');
            $table->timestamp('promotional_ends_at')->nullable()->after('promotional_tier_required');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn(['is_promotional', 'promotional_tier_required', 'promotional_ends_at']);
        });
    }
};
