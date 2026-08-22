<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TagSeeder extends Seeder
{
    private const TAGS = [
        'Bio',
        'Hydratant',
        'Anti-âge',
        'Peau sensible',
        'Sans parfum',
        'Vegan',
        'Cheveux crépus',
        'SPF',
        'Nouveauté',
        'Best-seller',
        'Hypoallergénique',
        'Naturel',
        'Made in Senegal',
        'Édition limitée',
    ];

    public function run(): void
    {
        foreach (self::TAGS as $name) {
            Tag::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            );
        }
    }
}
