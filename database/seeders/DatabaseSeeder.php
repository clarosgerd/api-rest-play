<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'first_name' => 'Admin',
            'last_name' => 'Demo',
            'email' => 'admin@demo.com',
            'password_hash' => bcrypt('password'),
            'role' => 'admin',
            'subscription_tier' => 'premium',
            'balance' => 0,
        ]);

        $this->call(CountriesSeeder::class);
    }
}
