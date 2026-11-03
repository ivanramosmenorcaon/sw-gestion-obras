<?php

namespace App\Filament\Actions;

use App\Filament\Resources\InvoiceSeries\InvoiceSeriesResource;
use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use App\Filament\Resources\WorkOrderPhases\WorkOrderPhaseResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\InvoiceSeries;
use App\Models\WorkOrder;
use App\Models\WorkOrderPhase;
use App\Support\SalesBilling;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

final class BillWorkOrder
{
    public static function make(): Action
    {
        return Action::make('billPhases')->label(__('billing.from_order'))
            ->authorize(fn (WorkOrder $record): bool => SalesInvoiceResource::canCreate() && WorkOrderResource::canView($record))
            ->schema([
                Select::make('invoice_series_id')->label(__('billing.fields.invoice_series_id'))->required()->searchable()
                    ->options(fn (): array => InvoiceSeries::where('kind', 'standard')->where('is_active', true)->pluck('name', 'id')->all())
                    ->createOptionForm(fn (Schema $schema): Schema => self::seriesForm($schema))
                    ->createOptionUsing(fn (array $data, Schema $schema): int => self::createSeries($data, $schema))
                    ->createOptionAction(fn (Action $action): Action => $action->authorize(fn (): bool => InvoiceSeriesResource::canCreate())->databaseTransaction()),
                Select::make('phase_ids')->label(__('billing.fields.phase_ids'))->multiple()->required()->searchable()
                    ->options(fn (WorkOrder $record): array => $record->workOrderPhases()->pluck('name', 'id')->all())
                    ->createOptionForm(function (Schema $schema, WorkOrder $record): Schema {
                        $schema = WorkOrderPhaseResource::form($schema->model(WorkOrderPhase::class)->operation('create'));
                        foreach ($schema->getFlatFields(withHidden: true) as $field) {
                            if ($field->getName() === 'work_order_id') {
                                $field->default($record->id)->disabled()->dehydrated();
                            }
                        }

                        return $schema;
                    })
                    ->createOptionUsing(function (array $data, Schema $schema, WorkOrder $record): int {
                        abort_unless(WorkOrderPhaseResource::canCreate() && WorkOrderResource::canView($record), 403);

                        return DB::transaction(function () use ($data, $schema, $record): int {
                            $phase = WorkOrderPhase::create(array_replace($data, ['work_order_id' => $record->id]));
                            $schema->model($phase)->saveRelationships();

                            return $phase->id;
                        });
                    })
                    ->createOptionAction(fn (Action $action): Action => $action->authorize(fn (): bool => WorkOrderPhaseResource::canCreate())->databaseTransaction()),
                TextInput::make('percentage')->label(__('billing.fields.percentage'))->numeric()->minValue('0.000001')->maxValue(100)->default('100')->required(),
            ])->action(function (WorkOrder $record, array $data, Component $livewire): void {
                abort_unless(SalesInvoiceResource::canCreate() && WorkOrderResource::canView($record), 403);
                $invoice = app(SalesBilling::class)->fromWorkOrder($record, (int) $data['invoice_series_id'], $data['phase_ids'], (string) $data['percentage']);
                $livewire->redirect(SalesInvoiceResource::getUrl('edit', ['record' => $invoice]));
            });
    }

    private static function seriesForm(Schema $schema): Schema
    {
        $schema = InvoiceSeriesResource::form($schema->model(InvoiceSeries::class)->operation('create'));
        foreach ($schema->getFlatFields(withHidden: true) as $field) {
            if ($field->getName() === 'kind') {
                $field->default('standard')->disabled()->dehydrated();
            }
        }

        return $schema;
    }

    private static function createSeries(array $data, Schema $schema): int
    {
        abort_unless(InvoiceSeriesResource::canCreate(), 403);

        return DB::transaction(function () use ($data, $schema): int {
            $series = InvoiceSeries::create(array_replace($data, ['kind' => 'standard']));
            $schema->model($series)->saveRelationships();

            return $series->id;
        });
    }
}
