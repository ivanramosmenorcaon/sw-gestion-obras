<?php

namespace App\Support;

use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\Budgets\Pages\EditBudget;
use App\Filament\Resources\ProjectItems\ProjectItemResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Budget;
use App\Models\CompanySetting;
use App\Models\ProjectChapter;
use App\Models\ProjectItem;
use App\Models\Unit;
use App\Models\WorkOrder;
use App\Models\WorkOrderLine;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class BudgetWorkflow
{
    /** @return Collection<int, ProjectItem> */
    public function publish(Budget $budget): Collection
    {
        return DB::transaction(function () use ($budget): Collection {
            $current = Budget::whereKey($budget->id)->lockForUpdate()->firstOrFail();
            abort_unless(OperationalAccess::allows(Budget::class, 'update', $current) && OperationalAccess::allows(ProjectItem::class, 'create'), 403);
            if ($current->status !== 'accepted' || in_array($current->project->status, ['completed', 'cancelled'], true)) {
                throw ValidationException::withMessages(['name' => 'Incorpore un presupuesto aceptado de una obra abierta.']);
            }
            $items = ProjectItem::where('source_budget_id', $current->id)->orderBy('source_budget_position')->get();
            if ($items->isNotEmpty()) {
                return $items;
            }
            $ancestor = $current->revises_id;
            $visited = [];
            while ($ancestor && ! isset($visited[$ancestor])) {
                $visited[$ancestor] = true;
                if (ProjectItem::where('source_budget_id', $ancestor)->exists()) {
                    throw ValidationException::withMessages(['name' => 'La revisión sustituye un alcance ya incorporado. Resuelva esa sustitución antes de añadirlo de nuevo; para trabajo adicional utilice un extra.']);
                }
                $ancestor = Budget::whereKey($ancestor)->value('revises_id');
            }
            foreach ($current->items as $position => $snapshot) {
                $unit = Unit::where('is_active', true)->where(fn ($q) => $q->where('symbol', $snapshot['unit'])->orWhere('name', $snapshot['unit']))->first();
                if (! $unit) {
                    throw ValidationException::withMessages(['name' => 'Registre una unidad activa con símbolo «'.$snapshot['unit'].'» antes de incorporar las partidas.']);
                }
                $parentId = null;
                foreach ($snapshot['chapter_path'] ?? [] as $chapterData) {
                    $chapter = ProjectChapter::where('project_id', $current->project_id)->where('code', $chapterData['code'])->first();
                    if (! $chapter) {
                        $chapter = ProjectChapter::create(['project_id' => $current->project_id, 'parent_id' => $parentId, 'code' => $chapterData['code'], 'name' => $chapterData['name'], 'position' => 1]);
                    }
                    if ($chapter->parent_id !== $parentId) {
                        throw ValidationException::withMessages(['name' => 'El capítulo de la oferta no coincide con la jerarquía actual de la obra. Revise sus códigos.']);
                    }
                    $parentId = $chapter->id;
                }
                $sourceId = $snapshot['source_project_item_id'] ?? null;
                if (! $sourceId && $current->source_work_order_id && ($snapshot['source_line_id'] ?? 0) > 0) {
                    $sourceId = WorkOrderLine::where('work_order_id', $current->source_work_order_id)->whereKey($snapshot['source_line_id'])->value('project_item_id');
                }
                $item = $sourceId ? ProjectItem::whereKey($sourceId)->where('project_id', $current->project_id)->lockForUpdate()->first() : null;
                if ($sourceId && ! $item) {
                    throw ValidationException::withMessages(['name' => 'La partida de origen no pertenece a la obra.']);
                }
                if ($item && ($item->source_budget_id || $item->workOrderLines()->whereHas('workOrder', fn ($q) => $q->where('purpose', 'intervention')->where('status', '!=', 'cancelled'))->exists())) {
                    throw ValidationException::withMessages(['name' => 'La partida de origen ya está contratada o encargada. Revise su alcance antes de incorporarla de nuevo.']);
                }
                $item ??= new ProjectItem;
                $item->fill([
                    'project_id' => $current->project_id, 'project_chapter_id' => $parentId, 'position' => $position + 1,
                    'source_code' => $snapshot['source_code'] ?? null, 'description' => $snapshot['description'],
                    'unit_id' => $unit->id, 'base_unit_id' => $unit->id, 'unit_label' => $snapshot['unit'],
                    'planned_quantity' => $snapshot['quantity'], 'estimated_unit_sale_price' => $snapshot['price'],
                    'discount_percentage' => $snapshot['discount_percentage'] ?? '0',
                    'measurement_details' => $snapshot['measurement_details'] ?? [], 'budget_breakdown' => $snapshot['budget_breakdown'] ?? [],
                ]);
                $item->forceFill(['source_budget_id' => $current->id, 'source_budget_position' => $position + 1])->save();
                $items->push($item);
            }

            return $items;
        }, 3);
    }

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
            $items = $this->publish($current);
            $order = new WorkOrder(['project_id' => $current->project_id, 'reference' => 'OT-'.Str::ulid(), 'name' => $current->name, 'instructions' => $current->terms]);
            $order->save();
            $phase = $order->workOrderPhases()->create(['code' => '01', 'name' => 'Ejecución', 'position' => 1]);
            foreach ($items as $index => $item) {
                $order->workOrderLines()->create([
                    'project_id' => $current->project_id, 'project_item_id' => $item->id,
                    'work_order_phase_id' => $phase->id, 'position' => $index + 1,
                    'planned_quantity' => $item->planned_quantity,
                    'notes' => 'Presupuesto '.$current->reference.' · partida '.$item->id,
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
            Action::make('executeOffer')->label('Incorporar partidas a la obra')->visible(fn (): bool => $page->getRecord()->status === 'accepted')
                ->authorize(fn (): bool => $allowed() && ProjectItemResource::canCreate())
                ->requiresConfirmation()->modalDescription('Se incorporará el alcance aceptado a las partidas de la obra. Después podrá repartirlo entre intervenciones sin duplicarlo. Esta acción no crea una orden de trabajo.')
                ->action(function () use ($page): void {
                    app(self::class)->publish($page->getRecord());
                    $page->redirect(ProjectResource::getUrl('edit', ['record' => $page->getRecord()->project_id]));
                }),
        ];
    }
}
