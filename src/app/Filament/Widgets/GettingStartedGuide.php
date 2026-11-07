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
use App\Filament\Resources\ProjectItems\ProjectItemResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\PurchaseDeliveryNotes\PurchaseDeliveryNoteResource;
use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use App\Filament\Resources\Warehouses\WarehouseResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Filament\Resources\WorkTimeEntries\WorkTimeEntryResource;
use App\Models\Budget;
use App\Models\ContractorAgreement;
use App\Models\ContractorCertificate;
use App\Models\EmployeeAbsence;
use App\Models\InvoiceMaturity;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\PaymentReversal;
use App\Models\ProjectActivity;
use App\Models\ProjectDocument;
use App\Models\ProjectExpense;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WorkProgress;
use App\Models\WorkTimeEntry;
use App\Models\WorkTimeReversal;
use App\Support\OperationalAccess;
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
            'products' => ProductResource::class, 'projects' => ProjectResource::class, 'project_items' => ProjectItemResource::class,
            'orders' => WorkOrderResource::class, 'budgets' => BudgetResource::class,
            'employees' => EmployeeResource::class, 'rates' => LaborCostRateResource::class,
            'receipts' => PurchaseDeliveryNoteResource::class, 'warehouses' => WarehouseResource::class,
            'consumptions' => MaterialConsumptionResource::class, 'times' => WorkTimeEntryResource::class,
            'invoices' => SalesInvoiceResource::class, 'series' => InvoiceSeriesResource::class,
        ];
        $extraModels = [
            'stock' => StockMovement::class, 'applications' => PaymentApplication::class,
            'maturities' => InvoiceMaturity::class, 'refunds' => PaymentReversal::class,
            'absences' => EmployeeAbsence::class, 'agreements' => ContractorAgreement::class,
            'certificates' => ContractorCertificate::class, 'purchases' => PurchaseInvoice::class,
            'purchase_orders' => PurchaseOrder::class, 'expenses' => ProjectExpense::class,
            'payments' => Payment::class, 'documents' => ProjectDocument::class,
            'activities' => ProjectActivity::class, 'progress' => WorkProgress::class,
            'corrections' => WorkTimeReversal::class, 'users' => User::class,
        ];
        foreach ($extraModels as $key => $model) {
            $resources[$key] = Filament::getModelResource($model);
        }
        $links = [];
        foreach ($resources as $key => $resource) {
            if ($resource::canViewAny()) {
                $links[$key] = ['label' => $resource::getPluralModelLabel(), 'url' => $resource::getUrl('index')];
            }
        }

        $pending = [];
        foreach ([
            'budgets' => [Budget::class, 'draft', 'Ofertas en preparación'],
            'times' => [WorkTimeEntry::class, 'draft', 'Partes pendientes de aprobación'],
            'activities' => [ProjectActivity::class, 'draft', 'Incidencias e hitos abiertos'],
            'purchases' => [PurchaseInvoice::class, 'draft', 'Facturas recibidas pendientes'],
            'documents' => [ProjectDocument::class, 'draft', 'Documentos pendientes de archivar'],
        ] as $key => [$model, $status, $label]) {
            if (isset($links[$key])) {
                $pending[] = ['label' => $label, 'count' => OperationalAccess::scope($model::query())->where('status', $status)->count(), 'url' => $links[$key]['url']];
            }
        }
        $exports = OperationalAccess::canSeeCosts() ? [
            ['label' => 'Exportar líneas de facturas recibidas', 'url' => route('operations.export', ['type' => 'purchase-lines'])],
            ['label' => 'Exportar devoluciones de dinero', 'url' => route('operations.export', ['type' => 'refunds'])],
            ['label' => 'Exportar aplicaciones de anticipos', 'url' => route('operations.export', ['type' => 'applications'])],
            ['label' => 'Exportar horas para asesoría', 'url' => route('operations.export', ['type' => 'hours'])],
            ['label' => 'Exportar facturas recibidas', 'url' => route('operations.export', ['type' => 'purchases'])],
            ['label' => 'Exportar cobros y pagos', 'url' => route('operations.export', ['type' => 'payments'])],
        ] : [];

        return ['pending' => $pending, 'exports' => $exports, 'role' => OperationalAccess::ROLES[auth()->user()->role] ?? 'Usuario', 'steps' => array_filter(__('onboarding.steps'), fn (array $step): bool => array_intersect($step['links'], array_keys($links)) !== []), 'links' => $links, 'quickLinks' => array_intersect_key($links, array_flip(['projects', 'orders', 'receipts', 'times', 'invoices']))];
    }
}
