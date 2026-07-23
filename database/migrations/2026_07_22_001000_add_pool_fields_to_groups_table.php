<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->decimal('bet_amount', 10, 2)->nullable()->default(0)->after('max_members');
            $table->decimal('prize_1st_pct', 5, 2)->nullable()->default(60)->after('bet_amount');
            $table->decimal('prize_2nd_pct', 5, 2)->nullable()->default(30)->after('prize_1st_pct');
            $table->decimal('prize_3rd_pct', 5, 2)->nullable()->default(10)->after('prize_2nd_pct');
            $table->boolean('pool_active')->default(false)->after('prize_3rd_pct');
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn([
                'bet_amount', 'prize_1st_pct', 'prize_2nd_pct',
                'prize_3rd_pct', 'pool_active',
            ]);
        });
    }
};
