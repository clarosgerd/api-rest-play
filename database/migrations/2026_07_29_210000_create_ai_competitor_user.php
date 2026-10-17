<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->insert([
            'first_name' => 'Franco',
            'last_name' => 'Marcelo (Chelo)',
            'email' => 'franco.chelo@soccerapi.ai',
            'password_hash' => Hash::make('not-a-real-login-password'),
            'balance' => 0,
            'verified' => '1',
        ]);
    }

    public function down(): void
    {
        DB::table('users')->where('email', 'franco.chelo@soccerapi.ai')->delete();
    }
};
