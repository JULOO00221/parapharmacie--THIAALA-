<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductCategorySeeder extends Seeder
{
    /**
     * 2 top-level parents with 10 sub-categories total = 12 categories,
     * within the requested 8-12 range. Products are assigned directly to
     * the leaf (child) categories.
     */
    private const CATEGORIES = [
        'Soins & beauté' => [
            'Crèmes hydratantes',
            'Nettoyants visage',
            'Savons & gels douche',
            'Huiles corporelles',
            'Shampoings',
            'Soins capillaires',
        ],
        'Hygiène & santé' => [
            'Hygiène bébé',
            'Hygiène intime',
            'Compléments alimentaires',
            'Solaires',
        ],
    ];

    public function run(): void
    {
        $sortOrder = 0;

        foreach (self::CATEGORIES as $parentName => $children) {
            $parent = ProductCategory::updateOrCreate(
                ['slug' => Str::slug($parentName)],
                [
                    'name' => $parentName,
                    'parent_id' => null,
                    'is_active' => true,
                    'sort_order' => $sortOrder++,
                ]
            );

            $childSortOrder = 0;

            foreach ($children as $childName) {
                ProductCategory::updateOrCreate(
                    ['slug' => Str::slug($childName)],
                    [
                        'name' => $childName,
                        'parent_id' => $parent->id,
                        'is_active' => true,
                        'sort_order' => $childSortOrder++,
                    ]
                );
            }
        }
    }
}
