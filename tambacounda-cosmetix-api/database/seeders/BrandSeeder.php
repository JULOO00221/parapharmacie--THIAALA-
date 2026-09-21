<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BrandSeeder extends Seeder
{
    /**
     * Fictional brand names, plausible for a Senegalese parapharmacy —
     * none of these correspond to real companies or products.
     *
     * Public because catalog:deactivate-demo reads it to know which brands
     * are demo ones: duplicating the list there would let the two drift
     * apart, and a brand forgotten on one side would stay visible on the
     * storefront.
     */
    public const BRANDS = [
        ['name' => 'Teranga Cosmétique', 'website' => 'https://teranga-cosmetique.example'],
        ['name' => 'Baobab Soins', 'website' => 'https://baobab-soins.example'],
        ['name' => 'Sahel Pharma', 'website' => null],
        ['name' => 'Dakar Beauté', 'website' => 'https://dakar-beaute.example'],
        ['name' => 'Casamance Bio', 'website' => null],
        ['name' => 'Ndar Naturel', 'website' => 'https://ndar-naturel.example'],
        ['name' => 'Gorée Soins', 'website' => null],
        ['name' => 'Kaolack Vital', 'website' => 'https://kaolack-vital.example'],
        ['name' => 'Thiès Pharma', 'website' => null],
        ['name' => 'Sénégal Éclat', 'website' => 'https://senegal-eclat.example'],
    ];

    public function run(): void
    {
        foreach (self::BRANDS as $brand) {
            Brand::updateOrCreate(
                ['slug' => Str::slug($brand['name'])],
                [
                    'name' => $brand['name'],
                    'website' => $brand['website'],
                    'is_active' => true,
                ]
            );
        }
    }
}
