<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->integer('exact_score_points')->default(10)->after('pool_active');
            $table->integer('match_result_points')->default(5)->after('exact_score_points');
            $table->integer('correct_goal_points')->default(3)->after('match_result_points');
            $table->integer('unique_exact_points')->default(15)->after('correct_goal_points');
            $table->decimal('multiplier_dieciseisavos', 3, 1)->default(1.5)->after('unique_exact_points');
            $table->decimal('multiplier_octavos', 3, 1)->default(2)->after('multiplier_dieciseisavos');
            $table->decimal('multiplier_cuartos', 3, 1)->default(3)->after('multiplier_octavos');
            $table->decimal('multiplier_semifinal', 3, 1)->default(5)->after('multiplier_cuartos');
            $table->decimal('multiplier_final', 3, 1)->default(10)->after('multiplier_semifinal');
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn([
                'exact_score_points', 'match_result_points', 'correct_goal_points',
                'unique_exact_points', 'multiplier_dieciseisavos', 'multiplier_octavos',
                'multiplier_cuartos', 'multiplier_semifinal', 'multiplier_final',
            ]);
        });
    }
};
