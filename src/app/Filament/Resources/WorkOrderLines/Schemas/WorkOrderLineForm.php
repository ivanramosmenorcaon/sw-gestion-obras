<?php

namespace App\Filament\Resources\WorkOrderLines\Schemas;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Support\ProjectCosting;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WorkOrderLineForm
{
    /** @param array<string, mixed> $context */
    public static function configure(Schema $schema, array $context = []): Schema
    {
        return $schema->columns(2)->components([
            (array_key_exists('project_id', $context) ? Hidden::make('project_id')->default($context['project_id']) : Select::make('project_id')->relationship('project', 'name')->searchable()->preload()->live()->afterStateUpdated(function (Set $set): void {
                $set('work_order_id', null);
                $set('project_chapter_id', null);
                $set('work_order_phase_id', null);
            }))->label(__('business.fields.project_id'))->required(),
            (array_key_exists('work_order_id', $context) ? Hidden::make('work_order_id')->default($context['work_order_id']) : Select::make('work_order_id')->relationship('workOrder', 'name', modifyQueryUsing: function (Builder $query, Get $get, ?Model $record): Builder {
                $query->where('project_id', $get('project_id'));

                return $query;
            })->searchable()->preload()->live()->afterStateUpdated(function (Set $set): void {
                $set('work_order_phase_id', null);
            }))->label(__('business.fields.work_order_id'))->required(),
            (array_key_exists('project_chapter_id', $context) ? Hidden::make('project_chapter_id')->default($context['project_chapter_id']) : Select::make('project_chapter_id')->relationship('projectChapter', 'name', modifyQueryUsing: function (Builder $query, Get $get, ?Model $record): Builder {
                $query->where('project_id', $get('project_id'));

                return $query;
            })->searchable()->preload())->label(__('business.fields.project_chapter_id')),
            (array_key_exists('work_order_phase_id', $context) ? Hidden::make('work_order_phase_id')->default($context['work_order_phase_id']) : Select::make('work_order_phase_id')->relationship('workOrderPhase', 'name', modifyQueryUsing: function (Builder $query, Get $get, ?Model $record): Builder {
                $query->where('work_order_id', $get('work_order_id'));

                return $query;
            })->searchable()->preload())->label(__('business.fields.work_order_phase_id')),
            TextInput::make('position')->label(__('business.fields.position'))->required()->numeric()->minValue(1)->integer(),
            TextInput::make('source_code')->label('Código de partida')->maxLength(80),
            Select::make('type')->options(['material' => __('business.options.material'), 'service' => __('business.options.service'), 'labor' => __('business.options.labor'), 'rental' => __('business.options.rental')])->label(__('business.fields.type'))->required(),
            (array_key_exists('product_id', $context) ? Hidden::make('product_id')->default($context['product_id']) : Select::make('product_id')->relationship('product', 'name')->searchable()->preload()->live()->afterStateUpdated(function (Set $set, mixed $state): void {
                $set('product_unit_id', null);
                $product = Product::find($state);
                if ($product) {
                    $set('description', $product->name);
                    $set('type', $product->type);
                    $set('unit_id', $product->base_unit_id);
                    $set('base_unit_id', $product->base_unit_id);
                    $set('base_unit_factor', '1');
                    $set('unit_label', $product->baseUnit->symbol);
                    $quote = app(ProjectCosting::class)->productQuote($product->id, today('Europe/Madrid')->toDateString());
                    $set('estimated_unit_cost', $quote['cost'] ?? '0');
                    $set('estimated_unit_sale_price', $quote['sale'] ?? '0');
                }
            }))->label(__('business.fields.product_id')),
            (array_key_exists('product_unit_id', $context) ? Hidden::make('product_unit_id')->default($context['product_unit_id']) : Select::make('product_unit_id')->relationship('productUnit', 'name', modifyQueryUsing: function (Builder $query, Get $get, ?Model $record): Builder {
                $query->where('product_id', $get('product_id'));

                return $query;
            })->searchable()->preload()->live()->afterStateUpdated(function (Set $set, ?string $state): void {
                $presentation = ProductUnit::find($state);
                if (! $presentation) {
                    return;
                } $set('unit_id', $presentation->unit_id);
                $set('base_unit_id', $presentation->product->base_unit_id);
                $set('unit_label', $presentation->name);
                $set('base_unit_factor', $presentation->base_unit_factor);
                $quote = app(ProjectCosting::class)->productQuote($presentation->product_id, today('Europe/Madrid')->toDateString(), $presentation->base_unit_factor);
                if ($quote) {
                    $set('estimated_unit_cost', $quote['cost']);
                    $set('estimated_unit_sale_price', $quote['sale']);
                }
            }))->label(__('business.fields.product_unit_id')),
            Textarea::make('description')->rows(3)->columnSpanFull()->label(__('business.fields.description'))->required(),
            (array_key_exists('unit_id', $context) ? Hidden::make('unit_id')->default($context['unit_id']) : Select::make('unit_id')->relationship('unit', 'name')->searchable()->preload())->label(__('business.fields.unit_id'))->required(),
            TextInput::make('unit_label')->label(__('business.fields.unit_label'))->required()->maxLength(100),
            (array_key_exists('base_unit_id', $context) ? Hidden::make('base_unit_id')->default($context['base_unit_id']) : Select::make('base_unit_id')->relationship('baseUnit', 'name')->searchable()->preload())->label(__('business.fields.base_unit_id'))->required(),
            TextInput::make('base_unit_factor')->label(__('business.fields.base_unit_factor'))->required()->default('1')->numeric()->minValue(0.000000000001)->helperText(__('business.helpers.factor')),
            TextInput::make('planned_quantity')->label(__('business.fields.planned_quantity'))->required()->default('0')->numeric()->minValue(0),
            TextInput::make('estimated_unit_cost')->label(__('business.fields.estimated_unit_cost'))->required()->default('0')->numeric()->minValue(0),
            TextInput::make('estimated_unit_sale_price')->label(__('business.fields.estimated_unit_sale_price'))->required()->default('0')->numeric()->minValue(0),
            Textarea::make('notes')->rows(3)->columnSpanFull()->label(__('business.fields.notes')),
            Repeater::make('measurement_details')
                ->label('Desglose de medición del arquitecto')
                ->helperText('Datos originales del archivo, incluidos los descuentos negativos. Se conserva el total de medición indicado en el documento.')
                ->schema([
                    TextInput::make('label')->label('Descripción')->columnSpanFull(),
                    TextInput::make('units')->label('Unidades'),
                    TextInput::make('length')->label('Largo'),
                    TextInput::make('width')->label('Ancho'),
                    TextInput::make('height')->label('Alto'),
                    TextInput::make('subtotal')->label('Parcial'),
                    TextInput::make('source_row')->label('Fila o registro de origen'),
                ])
                ->columns(3)->columnSpanFull()->disabled()->dehydrated(false)
                ->visible(fn (?Model $record): bool => ! empty($record?->measurement_details)),
            Repeater::make('budget_breakdown')
                ->label('Descompuestos originales del BC3')
                ->helperText('Recursos y rendimientos del presupuesto original. Son datos de referencia; no generan productos ni asignaciones de personal.')
                ->schema([
                    TextInput::make('code')->label('Código'),
                    TextInput::make('parent_code')->label('Código padre'),
                    TextInput::make('name')->label('Recurso')->columnSpanFull(),
                    TextInput::make('unit')->label('Unidad'),
                    TextInput::make('factor')->label('Factor'),
                    TextInput::make('quantity')->label('Rendimiento'),
                    TextInput::make('price')->label('Precio unitario original'),
                    Textarea::make('description')->label('Descripción')->columnSpanFull(),
                ])
                ->itemLabel(fn (array $state): string => ($state['code'] ?? '').' · '.($state['name'] ?? ''))
                ->collapsed()->columns(3)->columnSpanFull()->disabled()->dehydrated(false)
                ->visible(fn (?Model $record): bool => ! empty($record?->budget_breakdown)),
        ]);
    }
}
