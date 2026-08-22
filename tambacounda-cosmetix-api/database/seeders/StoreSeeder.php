<?php

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    public function run(): void
    {
        Store::updateOrCreate(
            ['slug' => 'tambacounda-cosmetix'],
            [
                'name' => 'Tambacounda Cosmetix',
                'is_active' => true,
            ]
        );
    }
}
