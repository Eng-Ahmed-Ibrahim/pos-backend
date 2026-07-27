<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = [
            ['name' => 'كجم', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'عدد', 'created_at' => now(), 'updated_at' => now()],
        ];
        DB::table('units')->insert($units);
        DB::table('products')->whereNull('unit_id')->update(['unit_id' => 2]);
    }
}
