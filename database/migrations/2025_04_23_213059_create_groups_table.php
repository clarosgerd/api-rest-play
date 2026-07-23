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
        Schema::create('groups', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('group_id');
            $table->String('name');
            $table->String('description');
            $table->integer('creator_id')->unsigned();
            $table->boolean('is_public');
            $table->String('invitation_code')->unique();
            $table->integer('max_members')->nullable();
            $table->timestamp('created_at')->useCurrent = true;
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
