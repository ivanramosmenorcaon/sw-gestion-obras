<?php

namespace App\Support;

use App\Models\PriceListItem;
use App\Models\SalesInvoice;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;

final class SalesPrice
{
    public function resolve(SalesInvoice $invoice, int $productId, ?string $quantity = null): ?string
    {
        if ($quantity !== null && (! is_numeric($quantity) || BigDecimal::of($quantity)->compareTo('0') <= 0)) {
            return null;
        }
        $date = $invoice->operation_date->toDateString();
        $listId = $invoice->customer->customerSettings?->price_list_id;
        $items = PriceListItem::query()->where('product_id', $productId)->where('minimum_quantity', '<=', $quantity ?? '1')
            ->where(fn (Builder $query) => $query->whereNull('valid_from')->orWhereDate('valid_from', '<=', $date))
            ->where(fn (Builder $query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', $date))
            ->whereHas('productUnit', fn (Builder $query) => $query->where('code', 'base')->where('is_active', true)->where('available_for_sale', true))
            ->whereHas('priceList', fn (Builder $query) => $query->where('is_active', true)->where('currency_code', $invoice->currency_code)
                ->where(fn (Builder $query) => $query->whereNull('valid_from')->orWhereDate('valid_from', '<=', $date))
                ->where(fn (Builder $query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', $date)))
            ->when($listId, fn (Builder $query) => $query->where('price_list_id', $listId))
            ->orderByDesc('minimum_quantity')->orderByDesc('valid_from')->orderByDesc('id')->get();
        if (! $listId && $items->pluck('price_list_id')->unique()->count() !== 1) {
            return null;
        }

        return $items->first()?->unit_price;
    }
}
