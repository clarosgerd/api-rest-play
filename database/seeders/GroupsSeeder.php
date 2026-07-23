<?php

namespace Database\Seeders;

use App\Models\Groups;
use Illuminate\Database\Seeder;

class GroupsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Groups::factory()
            ->count(100)
            ->create();
    }
}
