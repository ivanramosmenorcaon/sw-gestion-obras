<?php

namespace App\Filament\Schemas;

use App\Models\Product;
use App\Models\ProjectActivity;
use App\Models\ProjectDocument;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Support\OperationsManager;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class OperationsForms
{
    public const LABELS = [
        'StockMovement' => 'Ajustes, traslados y devoluciones a proveedor',
        'PaymentApplication' => 'Aplicación de anticipos', 'InvoiceMaturity' => 'Vencimientos de facturas', 'PaymentReversal' => 'Devoluciones de cobros y pagos',
        'EmployeeAbsence' => 'Ausencias y calendario',
        'ProjectActivity' => 'Diario, incidencias y garantías',
        'ProjectExpense' => 'Gastos de obra',
        'ContractorAgreement' => 'Contratos de subcontratas',
        'ContractorCertificate' => 'Certificaciones de subcontratas',
        'PurchaseInvoice' => 'Facturas recibidas',
        'PurchaseOrder' => 'Pedidos de compra',
        'Payment' => 'Cobros, pagos y anticipos',
        'ProjectDocument' => 'Documentos de obra',
        'WorkProgress' => 'Avance y mediciones ejecutadas',
        'WorkTimeReversal' => 'Correcciones de partes',
    ];

    /** @param class-string<Model> $model */
    public static function form(Schema $schema, string $model): Schema
    {
        $name = class_basename($model);
        $fields = [];
        $attributes = (new $model)->getFillable();
        if (in_array('project_id', $attributes, true)) {
            $fields[] = Select::make('project_id')->label('Obra')->relationship('project', 'name')->searchable()->preload()->required()->live();
        }
        $relationships = [
            'warehouse_id' => ['warehouse', 'name', 'Almacén de destino (solo traslados)'],
            'purchase_delivery_line_id' => ['purchaseDeliveryLine', 'description', 'Lote de origen'],
            'payment_id' => ['payment', 'reference', 'Movimiento original'],
            'employee_id' => ['employee', 'name', 'Empleado'],
            'contractor_id' => ['contractor', 'legal_name', 'Contratista'],
            'supplier_id' => ['supplier', 'legal_name', 'Proveedor'],
            'party_id' => ['party', 'legal_name', 'Tercero'],
            'contractor_agreement_id' => ['contractorAgreement', 'reference', 'Contrato de subcontrata'],
            'work_order_line_id' => ['workOrderLine', 'description', 'Partida de ejecución'],
            'work_time_entry_id' => ['workTimeEntry', 'description', 'Parte confirmado a corregir'],
            'purchase_order_id' => ['purchaseOrder', 'reference', 'Pedido'],
            'purchase_delivery_note_id' => ['purchaseDeliveryNote', 'reference', 'Albarán recibido'],
            'sales_invoice_id' => ['salesInvoice', 'number', 'Factura de venta'],
            'purchase_invoice_id' => ['purchaseInvoice', 'reference', 'Factura recibida'],
            'work_order_phase_id' => ['workOrderPhase', 'name', 'Fase (opcional)'],
        ];
        foreach ($relationships as $key => [$relationship, $title, $label]) {
            if (! in_array($key, $attributes, true)) {
                continue;
            }
            $fields[] = Select::make($key)->label($label)
                ->disabled(fn (?Model $record): bool => $name === 'EmployeeAbsence' && $key === 'employee_id' && $record?->status === 'posted')
                ->relationship($relationship, $title, function (Builder $query, Get $get) use ($key): void {
                    if (in_array($key, ['contractor_agreement_id', 'purchase_order_id', 'purchase_invoice_id', 'work_order_line_id'], true) && $get('project_id')) {
                        $query->where('project_id', $get('project_id'));
                    }
                    if ($key === 'contractor_id') {
                        $query->whereHas('partyRoles', fn (Builder $q) => $q->where('role', 'subcontractor'));
                    }
                    if ($key === 'supplier_id') {
                        $query->whereHas('partyRoles', fn (Builder $q) => $q->whereIn('role', ['supplier', 'subcontractor']));
                    }
                    if ($key === 'work_time_entry_id') {
                        $query->where('status', 'posted');
                    }
                    if ($key === 'work_order_phase_id' && $get('project_id')) {
                        $query->whereHas('workOrder', fn (Builder $q) => $q->where('project_id', $get('project_id')));
                    }
                })->searchable()->preload()->required(in_array($key, ['employee_id', 'contractor_id', 'supplier_id', 'party_id', 'work_order_line_id', 'work_time_entry_id', 'payment_id'], true) || ($name === 'ContractorCertificate' && $key === 'contractor_agreement_id'));
        }
        $fields[] = TextInput::make('reference')->label('Referencia')->required()->maxLength(100);
        $fields[] = Textarea::make('description')->label($name === 'EmployeeAbsence' ? 'Descripción / motivo de la ausencia' : ($name === 'WorkTimeReversal' ? 'Motivo de la corrección' : 'Descripción / alcance'))->required()->maxLength(10000)->columnSpanFull();
        foreach (['operation_date' => 'Fecha de operación', 'starts_on' => 'Desde', 'ends_on' => 'Hasta', 'due_date' => 'Vencimiento / fecha prevista', 'valid_until' => 'Caducidad documental'] as $key => $label) {
            if (in_array($key, $attributes, true)) {
                $fields[] = DatePicker::make($key)->label($label)->disabled(fn (?Model $record): bool => $name === 'EmployeeAbsence' && $record?->status === 'posted')->default(today('Europe/Madrid'))->required(in_array($key, ['operation_date', 'starts_on', 'ends_on'], true) || $name === 'PurchaseInvoice' && $key === 'due_date');
            }
        }
        foreach (['amount' => 'Importe sin impuestos / movimiento', 'net_amount' => 'Base imponible', 'tax_amount' => 'Impuestos soportados', 'withholding_amount' => 'Retención fiscal', 'retention_amount' => 'Retención de garantía', 'retention_percentage' => 'Garantía pactada (%)', 'quantity' => 'Cantidad ejecutada en este parte', 'version' => 'Versión'] as $key => $label) {
            if (in_array($key, $attributes, true) && ! in_array($name, ['PurchaseOrder', 'PurchaseInvoice'], true)) {
                $fields[] = TextInput::make($key)->label($label)->numeric()->minValue($name === 'StockMovement' ? null : 0)->step($key === 'quantity' ? '0.000001' : '0.01')->required()->default($key === 'version' ? 1 : 0);
            }
        }
        if (in_array('category', $attributes, true)) {
            $options = match ($name) {
                'StockMovement' => ['adjustment' => 'Regularización de inventario (+/-)', 'transfer' => 'Traslado (cantidad negativa en origen)', 'supplier_return' => 'Devolución a proveedor (cantidad negativa)'],
                'ProjectExpense' => ['machinery' => 'Maquinaria / alquiler', 'travel' => 'Desplazamiento / dietas', 'transport' => 'Transporte', 'fees' => 'Tasas', 'indirect' => 'Indirectos', 'other' => 'Otros'],
                'ProjectActivity' => ['daily' => 'Diario', 'incident' => 'Incidencia', 'instruction' => 'Instrucción', 'milestone' => 'Hito', 'snag' => 'Repaso', 'warranty' => 'Garantía'],
                default => ['plan' => 'Plano', 'contract' => 'Contrato', 'accepted_offer' => 'Oferta aceptada', 'photo' => 'Fotografía', 'delivery' => 'Albarán original', 'prevention' => 'PRL / CAE / REA', 'other' => 'Otros'],
            };
            $fields[] = Select::make('category')->label('Tipo')->options($options)->required();
        }
        if ($name === 'Payment') {
            $fields[] = Select::make('direction')->label('Sentido')->options(['receipt' => 'Cobro', 'payment' => 'Pago'])->required();
            $fields[] = Select::make('method')->label('Método')->options(['transfer' => 'Transferencia', 'cash' => 'Efectivo', 'card' => 'Tarjeta', 'other' => 'Otro'])->required()->default('transfer');
        }
        if ($name === 'ProjectDocument') {
            $fields[] = FileUpload::make('path')->label('Archivo privado')->disk('local')->directory('project-documents')->visibility('private')
                ->preventFilePathTampering()->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->maxSize(15360)->required()->columnSpanFull();
        }

        if (in_array($name, ['PurchaseOrder', 'PurchaseInvoice'], true)) {
            $lineFields = [
                Select::make('product_id')->label('Producto / servicio (opcional)')->relationship('product', 'name')->searchable()->preload()->live()
                    ->afterStateUpdated(function ($state, Set $set): void {
                        $product = Product::find($state);
                        if ($product) {
                            $set('description', $product->name);
                            $set('unit_label', $product->baseUnit->symbol);
                        }
                    }),
                TextInput::make('description')->label('Descripción')->required()->maxLength(1000),
                TextInput::make('unit_label')->label('Unidad')->required()->maxLength(100)->default('ud'),
                TextInput::make('quantity')->label('Cantidad')->numeric()->minValue('0.000001')->step('0.000001')->required()->default(1),
                TextInput::make('unit_price')->label('Precio unitario')->numeric()->minValue(0)->step('0.000001')->required()->default(0),
                TextInput::make('discount_percentage')->label('Descuento (%)')->numeric()->minValue(0)->maxValue(100)->required()->default(0),
                TextInput::make('tax_percentage')->label('Impuesto (%)')->numeric()->minValue(0)->maxValue(100)->required()->default(0),
                TextInput::make('withholding_percentage')->label('Retención fiscal (%)')->numeric()->minValue(0)->maxValue(100)->required()->default(0),
                TextInput::make('retention_percentage')->label('Garantía (%)')->numeric()->minValue(0)->maxValue(100)->required()->default(0),
            ];
            if ($name === 'PurchaseInvoice') {
                $lineFields[] = Select::make('purchase_order_line_id')->label('Línea del pedido (opcional)')->relationship('purchaseOrderLine', 'description',
                    fn (Builder $query, Get $get) => $query->where('purchase_order_id', $get('../../purchase_order_id')))->searchable()->preload();
                $lineFields[] = Select::make('purchase_delivery_line_id')->label('Línea del albarán (opcional)')->relationship('purchaseDeliveryLine', 'description',
                    fn (Builder $query, Get $get) => $query->where('purchase_delivery_note_id', $get('../../purchase_delivery_note_id')))->searchable()->preload();
            }
            $fields[] = Repeater::make('lines')->label('Líneas del documento')->relationship()->orderColumn('position')
                ->schema($lineFields)->columns(3)->columnSpanFull()->defaultItems(1)->minItems(1)->reorderableWithButtons()
                ->addActionLabel('Añadir línea')->helperText('Los totales se calculan al guardar. Los porcentajes deben corresponder al documento del proveedor; no se asigna un impuesto automáticamente.');
            $fields[] = TextEntry::make('totals_help')->label('Totales guardados')->state(fn (?Model $record): string => $record ? ($name === 'PurchaseOrder' ? (string) $record->amount.' EUR sin impuestos' : 'Base '.$record->net_amount.' · Impuestos '.$record->tax_amount.' · Retención fiscal '.$record->withholding_amount.' · Garantía '.$record->retention_amount.' · A pagar '.$record->payable_total.' EUR') : 'Se calcularán desde las líneas al guardar.')->columnSpanFull();
        }

        return $schema->columns(2)->components($fields);
    }

    /** @param class-string<Model> $model */
    public static function table(Table $table, string $model): Table
    {
        $columns = [
            TextColumn::make('reference')->label('Referencia')->searchable()->sortable(),
            TextColumn::make('description')->label('Descripción')->limit(80)->wrap(),
        ];
        if (in_array('project_id', (new $model)->getFillable(), true)) {
            $columns[] = TextColumn::make('project.name')->label('Obra')->searchable();
        }
        foreach (['amount', 'payable_total', 'quantity'] as $attribute) {
            if (in_array($attribute, (new $model)->getFillable(), true) || $model === PurchaseInvoice::class && $attribute === 'payable_total') {
                $columns[] = TextColumn::make($attribute)->label($attribute === 'quantity' ? 'Cantidad' : 'Importe')->numeric(decimalPlaces: $attribute === 'quantity' ? 6 : 2);
            }
        }
        $columns[] = TextColumn::make('status')->label('Estado')->badge()->formatStateUsing(fn (string $state): string => ['draft' => 'Pendiente', 'posted' => 'Confirmado', 'cancelled' => 'Cancelado'][$state] ?? $state);
        $resource = Filament::getModelResource($model);
        $actions = [
            EditAction::make()->authorize(fn (Model $record): bool => $resource::canEdit($record)),
            ViewAction::make()->authorize(fn (Model $record): bool => $resource::canView($record)),
            Action::make('post')->label($model === ProjectActivity::class ? 'Cerrar / resolver' : 'Confirmar')->requiresConfirmation()->databaseTransaction()
                ->authorize(fn (Model $record): bool => $record->status === 'draft' && $resource::canEdit($record))
                ->action(fn (Model $record) => app(OperationsManager::class)->post($record)),
            Action::make('cancelDraft')->label('Cancelar pendiente')->color('danger')->requiresConfirmation()->databaseTransaction()
                ->authorize(fn (Model $record): bool => $record->status === 'draft' && $resource::canEdit($record))
                ->action(fn (Model $record) => app(OperationsManager::class)->cancel($record)),
        ];
        if (in_array($model, [PurchaseOrder::class, PurchaseInvoice::class], true)) {
            $actions[] = Action::make('print')->label('Imprimir')->authorize(fn (Model $record): bool => $resource::canView($record))
                ->url(fn (Model $record): string => route('documents.print', ['type' => $model === PurchaseOrder::class ? 'purchase-order' : 'purchase-invoice', 'id' => $record->id]))->openUrlInNewTab();
        }
        if ($model === ProjectDocument::class) {
            $actions[] = Action::make('download')->label('Descargar')->authorize(fn (Model $record): bool => $resource::canView($record))
                ->url(fn (Model $record): string => route('project-documents.download', $record));
        }

        return $table->dragReorderableColumns()->stickableColumns()->defaultSort('id', 'desc')->columns($columns)->filters([
            SelectFilter::make('status')->label('Estado')->options(['draft' => 'Pendiente', 'posted' => 'Confirmado', 'cancelled' => 'Cancelado']),
        ])->recordActions($actions);
    }
}
