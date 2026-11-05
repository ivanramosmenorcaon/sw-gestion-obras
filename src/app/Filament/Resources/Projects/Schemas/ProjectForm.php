<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Models\Project;
use App\Support\FiscalCatalog;
use App\Support\OperationalAccess;
use App\Support\ProjectEconomics;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProjectForm
{
    /** @param array<string, mixed> $context */
    public static function configure(Schema $schema, array $context = []): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('economic_summary')->label('Situación económica de la obra')->columnSpanFull()
                ->visible(fn (?Project $record): bool => $record?->exists && OperationalAccess::canSeeCosts())
                ->state(function (Project $record): string {
                    $totals = app(ProjectEconomics::class)->forProject($record);

                    return 'Ofertado aceptado: '.$totals['accepted'].' EUR · Coste real: '.$totals['actual'].' EUR · Contratado/pedido: '.$totals['committed'].' EUR · Facturado a cobrar: '.$totals['invoiced'].' EUR · Cobrado: '.$totals['received'].' EUR · Pagado: '.$totals['paid'].' EUR · Venta aceptada menos coste incurrido: '.$totals['sale_less_actual'].' EUR. Este último importe no es el margen final; faltan los costes pendientes de ejecución.';
                }),
            TextInput::make('reference')->label(__('business.fields.reference'))->required()->maxLength(80)->unique(ignoreRecord: true),
            TextInput::make('name')->label(__('business.fields.name'))->required()->maxLength(255),
            Textarea::make('description')->rows(3)->columnSpanFull()->label(__('business.fields.description')),
            (array_key_exists('customer_id', $context) ? Hidden::make('customer_id')->default($context['customer_id']) : Select::make('customer_id')->relationship('customer', 'legal_name', modifyQueryUsing: function (Builder $query, Get $get, ?Model $record): Builder {
                $query->whereHas('partyRoles', fn (Builder $roles): Builder => $roles->where('role', 'customer'));

                return $query;
            })->searchable()->preload())->label(__('business.fields.customer_id'))->required(),
            (array_key_exists('manager_id', $context) ? Hidden::make('manager_id')->default($context['manager_id']) : Select::make('manager_id')->relationship('manager', 'name')->searchable()->preload())->label(__('business.fields.manager_id')),
            Select::make('status')->options(['draft' => __('business.options.draft'), 'quoted' => __('business.options.quoted'), 'approved' => __('business.options.approved'), 'in_progress' => __('business.options.in_progress'), 'on_hold' => __('business.options.on_hold'), 'completed' => __('business.options.completed'), 'cancelled' => __('business.options.cancelled')])->label(__('business.fields.status'))->required()->default('draft'),
            TextInput::make('currency_code')->label(__('business.fields.currency_code'))->required()->default('EUR')->maxLength(3)->helperText(__('business.helpers.currency')),
            TextInput::make('address_line_1')->label(__('business.fields.address_line_1'))->maxLength(255),
            TextInput::make('address_line_2')->label(__('business.fields.address_line_2'))->maxLength(255),
            TextInput::make('city')->label(__('business.fields.city'))->maxLength(120),
            TextInput::make('province')->label(__('business.fields.province'))->maxLength(120),
            TextInput::make('postal_code')->label(__('business.fields.postal_code'))->maxLength(20),
            Select::make('country_code')->options(FiscalCatalog::countries())->searchable()->label(__('business.fields.country_code'))->required()->default('ES')->helperText(__('business.helpers.country')),
            DatePicker::make('planned_start_date')->native(false)->displayFormat('d/m/Y')->label(__('business.fields.planned_start_date')),
            DatePicker::make('planned_end_date')->native(false)->displayFormat('d/m/Y')->label(__('business.fields.planned_end_date'))->afterOrEqual(fn (Get $get): ?string => $get('planned_start_date')),
            DatePicker::make('actual_start_date')->native(false)->displayFormat('d/m/Y')->label(__('business.fields.actual_start_date')),
            DatePicker::make('actual_end_date')->native(false)->displayFormat('d/m/Y')->label(__('business.fields.actual_end_date'))->afterOrEqual(fn (Get $get): ?string => $get('actual_start_date')),
            Textarea::make('notes')->rows(3)->columnSpanFull()->label(__('business.fields.notes')),
        ]);
    }
}
