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
        Schema::create('groupmembers', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('member_id', true);
            $table->integer('group_id')->unsigned();
            $table->integer('user_id')->unsigned();
            $table->dateTime('joined_at')->useCurrent = true;
            $table->boolean('is_admin');
        });
        Schema::table('groupmembers', function ($table) {
            $table->foreign('user_id')->references('user_id')->on('users');
            $table->foreign('group_id')->references('group_id')->on('groups');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('groupmembers');
    }
};
