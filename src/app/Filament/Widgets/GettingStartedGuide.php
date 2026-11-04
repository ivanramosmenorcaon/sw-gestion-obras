<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\CompanySettings\CompanySettingResource;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\InvoiceSeries\InvoiceSeriesResource;
use App\Filament\Resources\LaborCostRates\LaborCostRateResource;
use App\Filament\Resources\MaterialConsumptions\MaterialConsumptionResource;
use App\Filament\Resources\Parties\PartyResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\PurchaseDeliveryNotes\PurchaseDeliveryNoteResource;
use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use App\Filament\Resources\Warehouses\WarehouseResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Filament\Resources\WorkTimeEntries\WorkTimeEntryResource;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

class GettingStartedGuide extends Widget
{
    protected string $view = 'filament.widgets.getting-started-guide';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -100;

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return Filament::auth()->check();
    }

    /** @return array{steps: array<int, array<string, mixed>>, links: array<string, array{label: string, url: string}>, quickLinks: array<string, array{label: string, url: string}>} */
    protected function getViewData(): array
    {
        $resources = [
            'company' => CompanySettingResource::class, 'contacts' => PartyResource::class,
            'products' => ProductResource::class, 'projects' => ProjectResource::class,
            'orders' => WorkOrderResource::class, 'budgets' => BudgetResource::class,
            'employees' => EmployeeResource::class, 'rates' => LaborCostRateResource::class,
            'receipts' => PurchaseDeliveryNoteResource::class, 'warehouses' => WarehouseResource::class,
            'consumptions' => MaterialConsumptionResource::class, 'times' => WorkTimeEntryResource::class,
            'invoices' => SalesInvoiceResource::class, 'series' => InvoiceSeriesResource::class,
        ];
        $links = [];
        foreach ($resources as $key => $resource) {
            if ($resource::canViewAny()) {
                $links[$key] = ['label' => $resource::getPluralModelLabel(), 'url' => $resource::getUrl('index')];
            }
        }

        return ['steps' => __('onboarding.steps'), 'links' => $links, 'quickLinks' => array_intersect_key($links, array_flip(['projects', 'orders', 'receipts', 'times', 'invoices']))];
    }
}
