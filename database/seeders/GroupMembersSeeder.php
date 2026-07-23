<?php

namespace Database\Seeders;

use App\Models\GroupMembers;
use Illuminate\Database\Seeder;

class GroupMembersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        GroupMembers::factory()
            ->count(100)
            ->create();
    }
}
