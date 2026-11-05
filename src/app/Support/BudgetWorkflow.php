<?php

namespace App\Support;

use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\Budgets\Pages\EditBudget;
use App\Filament\Resources\WorkOrderLines\WorkOrderLineResource;
use App\Filament\Resources\WorkOrderPhases\WorkOrderPhaseResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\Budget;
use App\Models\CompanySetting;
use App\Models\Unit;
use App\Models\WorkOrder;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class BudgetWorkflow
{
    public function send(Budget $budget): void
    {
        DB::transaction(function () use ($budget): void {
            $current = Budget::whereKey($budget->id)->lockForUpdate()->firstOrFail();
            if ($current->status !== 'draft' || $current->items === []) {
                throw ValidationException::withMessages(['name' => 'Solo se puede preparar el envío de un borrador con partidas.']);
            }
            $current->forceFill([
                'status' => 'sent', 'sent_at' => now(),
                'commercial_snapshot' => [
                    'issuer' => CompanySetting::where('scope', 'company')->first()?->only(['legal_name', 'tax_identification_number', 'address_line_1', 'city', 'postal_code', 'country_code', 'email', 'phone']),
                    'customer' => $current->project->customer->only(['legal_name', 'tax_identification_number']),
                    'project' => $current->project->only(['reference', 'name', 'address_line_1', 'city']),
                ],
            ])->save();
        });
    }

    public function accept(Budget $budget, string $evidence): void
    {
        validator(['evidence' => $evidence], ['evidence' => ['required', 'string', 'min:5', 'max:10000']])->validate();
        DB::transaction(function () use ($budget, $evidence): void {
            $current = Budget::whereKey($budget->id)->lockForUpdate()->firstOrFail();
            if ($current->status !== 'sent' || $current->valid_until?->isBefore(today())) {
                throw ValidationException::withMessages(['evidence' => 'El presupuesto debe estar enviado y dentro de su validez.']);
            }
            $current->forceFill(['status' => 'accepted', 'accepted_at' => now(), 'accepted_by' => auth()->id(), 'acceptance_evidence' => $evidence])->save();
        });
    }

    public function revise(Budget $budget, bool $extra = false): Budget
    {
        return DB::transaction(function () use ($budget, $extra): Budget {
            $current = Budget::whereKey($budget->id)->lockForUpdate()->firstOrFail();
            $revision = $current->replicate(['reference', 'sent_at', 'accepted_at', 'accepted_by', 'acceptance_evidence', 'commercial_snapshot']);
            $revision->forceFill([
                'reference' => 'PRE-'.Str::ulid(), 'status' => 'draft',
                'revision' => $extra ? 1 : $current->revision + 1,
                'revises_id' => $extra ? null : $current->id, 'extra_for_id' => $extra ? $current->id : $current->extra_for_id,
                'valid_until' => null, 'items' => $extra ? [] : $current->items,
                'name' => ($extra ? 'Extra · ' : 'Revisión · ').$current->name,
            ])->save();

            return $revision;
        });
    }

    public function execute(Budget $budget): WorkOrder
    {
        return DB::transaction(function () use ($budget): WorkOrder {
            $current = Budget::whereKey($budget->id)->lockForUpdate()->firstOrFail();
            if ($current->status !== 'accepted') {
                throw ValidationException::withMessages(['name' => 'Acepte el presupuesto antes de crear la ejecución.']);
            }
            if ($existing = WorkOrder::where('accepted_budget_id', $current->id)->first()) {
                return $existing;
            }
            $units = [];
            foreach ($current->items as $item) {
                $units[$item['unit']] = Unit::where('is_active', true)->where(fn ($q) => $q->where('symbol', $item['unit'])->orWhere('name', $item['unit']))->first();
                if (! $units[$item['unit']]) {
                    throw ValidationException::withMessages(['name' => 'Registre una unidad activa con símbolo «'.$item['unit'].'» antes de convertir el presupuesto.']);
                }
            }
            $order = new WorkOrder(['project_id' => $current->project_id, 'reference' => 'OT-'.Str::ulid(), 'name' => $current->name, 'instructions' => $current->terms]);
            $order->save();
            $phase = $order->workOrderPhases()->create(['code' => '01', 'name' => 'Ejecución', 'position' => 1]);
            foreach ($current->items as $index => $item) {
                $unit = $units[$item['unit']];
                $order->workOrderLines()->create([
                    'project_id' => $current->project_id, 'work_order_phase_id' => $phase->id,
                    'position' => $index + 1, 'type' => 'service', 'description' => $item['description'],
                    'unit_id' => $unit->id, 'base_unit_id' => $unit->id, 'unit_label' => $item['unit'],
                    'discount_percentage' => $item['discount_percentage'] ?? '0',
                    'planned_quantity' => $item['quantity'], 'estimated_unit_sale_price' => $item['price'],
                    'notes' => 'Presupuesto '.$current->reference.' · línea '.$item['source_line_id'],
                    'source_code' => $item['source_code'] ?? null, 'measurement_details' => $item['measurement_details'] ?? [],
                ]);
            }

            $order->forceFill(['accepted_budget_id' => $current->id])->save();

            return $order;
        }, 3);
    }

    /** @return list<Action> */
    public static function actions(EditBudget $page): array
    {
        $allowed = fn (): bool => BudgetResource::canEdit($page->getRecord());

        return [
            Action::make('sendOffer')->label('Preparar envío')->visible(fn (): bool => $page->getRecord()->status === 'draft')->authorize($allowed)
                ->requiresConfirmation()->modalDescription('Se guardará y bloqueará esta versión. Imprima o descargue el documento y envíelo por su canal habitual; no se envía correo automáticamente.')
                ->action(function () use ($page): void {
                    $page->saveDraft();
                    app(self::class)->send($page->getRecord());
                    $page->redirect(BudgetResource::getUrl('edit', ['record' => $page->getRecord()]));
                }),
            Action::make('acceptOffer')->label('Registrar aceptación')->visible(fn (): bool => $page->getRecord()->status === 'sent')->authorize($allowed)
                ->schema([Textarea::make('evidence')->label('Evidencia: firmante, fecha y referencia del documento o correo')->required()->minLength(5)->maxLength(10000)])
                ->action(function (array $data) use ($page): void {
                    app(self::class)->accept($page->getRecord(), $data['evidence']);
                    $page->redirect(BudgetResource::getUrl('edit', ['record' => $page->getRecord()]));
                }),
            Action::make('reviseOffer')->label('Crear revisión')->authorize(fn (): bool => $allowed() && BudgetResource::canCreate())
                ->action(function () use ($page): void {
                    $revision = app(self::class)->revise($page->getRecord());
                    $page->redirect(BudgetResource::getUrl('edit', ['record' => $revision]));
                }),
            Action::make('extraOffer')->label('Presupuestar extra')->visible(fn (): bool => $page->getRecord()->status === 'accepted')
                ->authorize(fn (): bool => $allowed() && BudgetResource::canCreate())
                ->action(function () use ($page): void {
                    $extra = app(self::class)->revise($page->getRecord(), true);
                    $page->redirect(BudgetResource::getUrl('edit', ['record' => $extra]));
                }),
            Action::make('executeOffer')->label('Crear ejecución')->visible(fn (): bool => $page->getRecord()->status === 'accepted')
                ->authorize(fn (): bool => $allowed() && WorkOrderResource::canCreate() && WorkOrderLineResource::canCreate() && WorkOrderPhaseResource::canCreate())
                ->action(function () use ($page): void {
                    $order = app(self::class)->execute($page->getRecord());
                    $page->redirect(WorkOrderResource::getUrl('edit', ['record' => $order]));
                }),
        ];
    }
}
