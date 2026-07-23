<?php

namespace Database\Seeders;

use App\Models\Odds;
use Illuminate\Database\Seeder;

class OddsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Odds::factory(100)->create();
    }
}
