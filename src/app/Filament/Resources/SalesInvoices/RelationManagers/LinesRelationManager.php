<?php

namespace App\Filament\Resources\SalesInvoices\RelationManagers;

use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use App\Models\CompanySetting;
use App\Models\Product;
use App\Models\SalesInvoiceLine;
use App\Support\BusinessRecordValidator;
use App\Support\SalesPrice;
use Asmit\ResizedColumn\HasResizableColumn;
use Brick\Math\BigDecimal;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LinesRelationManager extends RelationManager
{
    use HasResizableColumn;

    protected static string $relationship = 'lines';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('billing.lines');
    }

    public function isReadOnly(): bool
    {
        return $this->getOwnerRecord()->status !== 'draft';
    }

    protected function canCreate(): bool
    {
        return ! $this->isReadOnly() && ! $this->getOwnerRecord()->rectifies_invoice_id && SalesInvoiceResource::canEdit($this->getOwnerRecord()) && parent::canCreate();
    }

    protected function canEdit(Model $record): bool
    {
        return ! $this->isReadOnly() && SalesInvoiceResource::canEdit($this->getOwnerRecord()) && parent::canEdit($record);
    }

    protected function canDelete(Model $record): bool
    {
        return $this->canEdit($record) && parent::canDelete($record);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('position')->label(__('billing.fields.position'))->integer()->minValue(1)->default(1)->required(),
            Select::make('product_id')->label(__('billing.fields.product_id'))->relationship('product', 'name', fn (Builder $query) => $query->where('is_active', true))->searchable()->preload()->live()
                ->disabled(fn (?SalesInvoiceLine $record): bool => (bool) $record?->rectifies_line_id)
                ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                    $product = Product::find($state);
                    $customer = $this->getOwnerRecord()->customer;
                    $company = CompanySetting::where('scope', 'company')->first();
                    if ($product) {
                        $set('description', $product->name);
                        $set('unit_label', $product->baseUnit?->symbol);
                    }
                    $set('unit_price', $product ? app(SalesPrice::class)->resolve($this->getOwnerRecord(), $product->id, filled($get('quantity')) ? (string) $get('quantity') : null) : null);
                    $set('tax_profile_id', $product?->sales_tax_profile_id ?? $customer->sales_tax_profile_id ?? $company?->sales_tax_profile_id);
                    $set('withholding_rate_id', $product?->sales_withholding_rate_id ?? $customer->sales_withholding_rate_id ?? $company?->sales_withholding_rate_id);
                }),
            TextInput::make('description')->label(__('billing.fields.description'))->required()->maxLength(500)->columnSpanFull(),
            TextInput::make('unit_label')->label(__('billing.fields.unit_label'))->maxLength(30),
            TextInput::make('quantity')->label(__('billing.fields.quantity'))->numeric()->required()->step('0.000001')->live(onBlur: true)
                ->afterStateUpdated(function (mixed $state, mixed $old, Get $get, Set $set, ?SalesInvoiceLine $record): void {
                    if (! $get('product_id') || $record?->rectifies_line_id || $record?->work_order_line_id) {
                        return;
                    }
                    $price = $get('unit_price');
                    $previous = app(SalesPrice::class)->resolve($this->getOwnerRecord(), (int) $get('product_id'), filled($old) ? (string) $old : null);
                    if (filled($price) && ($previous === null || ! is_numeric($price) || BigDecimal::of((string) $price)->compareTo($previous) !== 0)) {
                        return;
                    }
                    $set('unit_price', app(SalesPrice::class)->resolve($this->getOwnerRecord(), (int) $get('product_id'), filled($state) ? (string) $state : null));
                }),
            TextInput::make('unit_price')->label(__('billing.fields.unit_price'))->numeric()->minValue(0)->required()->step('0.000001')->disabled(fn (?SalesInvoiceLine $record): bool => (bool) $record?->rectifies_line_id),
            TextInput::make('discount_percentage')->label(__('billing.fields.discount_percentage'))->numeric()->minValue(0)->maxValue(100)->default(0)->required()->disabled(fn (?SalesInvoiceLine $record): bool => (bool) $record?->rectifies_line_id),
            Select::make('tax_profile_id')->label(__('billing.fields.tax_profile_id'))->relationship('taxProfile', 'name', fn (Builder $query) => $query->where('is_active', true))->searchable()->preload()->required()
                ->default(fn (): ?int => $this->getOwnerRecord()->customer->sales_tax_profile_id ?? CompanySetting::where('scope', 'company')->value('sales_tax_profile_id'))
                ->disabled(fn (?SalesInvoiceLine $record): bool => (bool) $record?->rectifies_line_id),
            Select::make('withholding_rate_id')->label(__('billing.fields.withholding_rate_id'))->relationship('withholdingRate', 'name', fn (Builder $query) => $query->where('kind', 'withholding')->where('is_active', true))->searchable()->preload()
                ->disabled(fn (?SalesInvoiceLine $record): bool => (bool) $record?->rectifies_line_id),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->modelLabel(__('billing.line'))->pluralModelLabel(__('billing.lines'))->recordTitleAttribute('description')->columns([
            TextColumn::make('position')->label(__('billing.fields.position'))->sortable(),
            TextColumn::make('description')->label(__('billing.fields.description'))->wrap(),
            TextColumn::make('workOrderLine.workOrderPhase.name')->label(__('billing.fields.phase_ids')),
            TextColumn::make('quantity')->label(__('billing.fields.quantity')),
            TextColumn::make('unit_label')->label(__('billing.fields.unit_label')),
            TextColumn::make('unit_price')->label(__('billing.fields.unit_price')),
            TextColumn::make('taxProfile.name')->state(fn (SalesInvoiceLine $record): ?string => $record->fiscal_snapshot ? $record->fiscal_snapshot['operation_class'].' · '.($record->fiscal_snapshot['tax_percentage'] ?? $record->fiscal_snapshot['exemption_code'] ?? '—') : $record->taxProfile?->name)->label(__('billing.fields.tax_profile_id')),
            TextColumn::make('withholdingRate.name')->state(fn (SalesInvoiceLine $record): ?string => $record->fiscal_snapshot ? $record->fiscal_snapshot['withholding_percentage'] : $record->withholdingRate?->name)->label(__('billing.fields.withholding_rate_id')),
            TextColumn::make('net_amount')->label(__('billing.fields.net_amount'))->money('EUR')->visible(fn (): bool => $this->isReadOnly()),
        ])->headerActions([CreateAction::make()->using(fn (array $data): Model => BusinessRecordValidator::inForm(fn (): Model => $this->getRelationship()->create($data), $this->getMountedActionSchema()?->getStatePath() ?? ''))->authorize(fn (): bool => $this->canCreate())->label(__('billing.add_line'))->visible(fn (): bool => ! $this->isReadOnly() && ! $this->getOwnerRecord()->rectifies_invoice_id)])
            ->recordActions([EditAction::make()->using(function (Model $record, array $data): Model {
                return BusinessRecordValidator::inForm(function () use ($record, $data): Model {
                    $record->update($data);

                    return $record;
                }, $this->getMountedActionSchema()?->getStatePath() ?? '');
            })->authorize(fn (Model $record): bool => $this->canEdit($record)), DeleteAction::make()->authorize(fn (Model $record): bool => $this->canDelete($record))])->dragReorderableColumns()->stickableColumns();
    }
}
