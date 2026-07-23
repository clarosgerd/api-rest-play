<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('status')->default('draft')->after('active');
            $table->unsignedInteger('created_by')->nullable()->after('status');
            $table->foreign('created_by')->references('user_id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn(['status', 'created_by']);
        });
    }
};
