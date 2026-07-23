<?php

namespace Database\Seeders;

use App\Models\Transactions;
use Illuminate\Database\Seeder;

class TransactionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        Transactions::factory(10)->create();
    }
}
