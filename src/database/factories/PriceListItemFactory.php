<?php

namespace Database\Factories;

use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PriceListItem> */
class PriceListItemFactory extends Factory
{
    protected $model = PriceListItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'price_list_id' => PriceList::factory(),
            'product_id' => Product::factory(),
            'product_unit_id' => fn (array $attributes): int => Product::findOrFail($attributes['product_id'])->productUnits()->firstOrFail()->id,
            'unit_price' => '1',
            'valid_from' => '2026-11-03',
        ];
    }
}
