<?php

namespace App\Filament\Resources\SalesInvoices\Tables;

use App\Models\SalesInvoice;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SalesInvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns([
            TextColumn::make('number')->label(__('billing.fields.number'))->placeholder(__('billing.options.draft'))->searchable(),
            TextColumn::make('customer.legal_name')->label(__('billing.fields.customer_id'))->searchable(),
            TextColumn::make('workOrder.reference')->label(__('billing.fields.work_order_id'))->searchable(),
            TextColumn::make('issue_date')->label(__('billing.fields.issue_date'))->date('d/m/Y')->sortable(),
            TextColumn::make('status')->label(__('billing.fields.status'))->badge()->formatStateUsing(fn (string $state): string => __('billing.options.'.$state)),
            TextColumn::make('environment')->label(__('billing.fields.environment'))->formatStateUsing(fn (?string $state): string => $state ? __('billing.options.'.$state) : '—'),
            TextColumn::make('gross_total')->state(fn (SalesInvoice $record): ?string => $record->status === 'issued' ? $record->gross_total : null)->placeholder('—')->label(__('billing.fields.gross_total'))->money('EUR'),
            TextColumn::make('payable_total')->state(fn (SalesInvoice $record): ?string => $record->status === 'issued' ? $record->payable_total : null)->placeholder('—')->label(__('billing.fields.payable_total'))->money('EUR'),
            TextColumn::make('billingRecord.submission.status')->label(__('billing.registry'))->formatStateUsing(fn (?string $state): string => $state ? __('billing.options.'.$state) : '—')->wrap(),
        ])->filters([SelectFilter::make('status')->label(__('billing.fields.status'))->options(collect(['draft', 'issued', 'cancelled'])->mapWithKeys(fn (string $state): array => [$state => __('billing.options.'.$state)]))])
            ->recordActions([ViewAction::make(), EditAction::make()])->dragReorderableColumns()->stickableColumns();
    }
}
