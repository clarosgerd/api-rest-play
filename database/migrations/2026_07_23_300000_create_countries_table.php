<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->increments('country_id');
            $table->string('name', 100)->unique();
            $table->string('code', 5)->unique();
            $table->string('flag_url', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
