<?php

namespace App\Filament\Forms\Components;

use App\Models\Party;
use App\Models\PartyRole;
use App\Models\ProductUnit;
use App\Models\ProjectChapter;
use App\Models\TaxRate;
use App\Models\WorkOrder;
use App\Models\WorkOrderPhase;
use App\Support\BusinessRecordValidator;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class RecordSelect extends Select
{
    public function relationship(string|Closure|null $name = null, string|Closure|null $titleAttribute = null, ?Closure $modifyQueryUsing = null, bool $ignoreRecord = false): static
    {
        parent::relationship($name, $titleAttribute, $modifyQueryUsing, $ignoreRecord);

        return $this
            ->createOptionForm(function (Schema $schema): Schema {
                $resource = $this->getOptionResource();
                abort_unless($resource, 404);

                $schema = $resource::form($schema->operation('create'));
                $context = $this->getCreationContext();
                foreach ($schema->getFlatFields(withHidden: true) as $field) {
                    if (array_key_exists($field->getName(), $context)) {
                        $field->default($context[$field->getName()])->disabled()->dehydrated();
                    }
                }

                return $schema;
            })
            ->createOptionUsing(function (array $data, Schema $schema): int|string {
                abort_unless($this->canCreateRelatedRecord(), 403);

                return BusinessRecordValidator::inForm(function () use ($data, $schema): int|string {
                    $record = $this->getRelationship()->newModelInstance();
                    $record->fill(array_replace($data, $this->getCreationContext()));
                    $record->save();

                    if ($record instanceof Party && ($role = $this->getPartyRole())) {
                        $record->partyRoles()->create(['role' => $role]);
                    }

                    $schema->model($record)->saveRelationships();
                    $this->syncParentContext($record);

                    return $record->getKey();
                }, $schema->getStatePath());
            })
            ->createOptionAction(fn (Action $action): Action => $action
                ->authorize(fn (): bool => $this->canCreateRelatedRecord())
                ->databaseTransaction()
                ->modalWidth('5xl')
                ->modalHeading(fn (): string => __('business.create_related', ['label' => $this->getOptionResource()::getModelLabel()])));
    }

    public function hasCreateOptionActionFormSchema(): bool
    {
        return parent::hasCreateOptionActionFormSchema() && $this->getOptionResource() !== null;
    }

    /** @return class-string<resource> | null */
    protected function getOptionResource(): ?string
    {
        if (! $this->hasRelationship() || ! Filament::getCurrentPanel()) {
            return null;
        }

        return Filament::getModelResource($this->getRelationship()->getRelated());
    }

    protected function canCreateRelatedRecord(): bool
    {
        $resource = $this->getOptionResource();

        if (! Filament::auth()->check() || $resource === null || ! $resource::canCreate()) {
            return false;
        }

        if ($this->getRelationship()->getRelated() instanceof Party && $this->getPartyRole() !== null) {
            $roleResource = Filament::getModelResource(PartyRole::class);

            return $roleResource !== null && $roleResource::canCreate();
        }

        return true;
    }

    protected function getPartyRole(): ?string
    {
        return match ($this->getName()) {
            'customer_id' => 'customer',
            'supplier_id' => 'supplier',
            'contractor_id' => 'subcontractor',
            default => null,
        };
    }

    /** @return array<string, mixed> */
    protected function getCreationContext(): array
    {
        $get = $this->makeGetUtility();
        $related = $this->getRelationship()->getRelated();
        $context = [];

        $parentField = match (true) {
            $related instanceof WorkOrder, $related instanceof ProjectChapter => 'project_id',
            $related instanceof WorkOrderPhase => 'work_order_id',
            $related instanceof ProductUnit => 'product_id',
            default => null,
        };
        if ($parentField !== null && filled($value = $get($parentField))) {
            $context[$parentField] = $value;
        }

        if ($related instanceof TaxRate) {
            $kind = match ($this->getName()) {
                'sales_withholding_rate_id', 'purchase_withholding_rate_id' => 'withholding',
                'surcharge_rate_id' => 'surcharge',
                'tax_rate_id' => ['01' => 'vat', '02' => 'ipsi', '03' => 'igic', '05' => 'other'][$get('tax_system') ?? '01'],
                default => null,
            };
            if ($kind !== null) {
                $context['kind'] = $kind;
            }
        }

        return $context;
    }

    protected function syncParentContext(Model $record): void
    {
        if ($record instanceof WorkOrder || $record instanceof ProjectChapter) {
            $this->setParentValue('project_id', $record->project_id);
        }
        if ($record instanceof WorkOrderPhase) {
            $this->setParentValue('work_order_id', $record->work_order_id);
            $this->setParentValue('project_id', $record->workOrder->project_id);
        }
        if ($record instanceof ProductUnit) {
            $this->setParentValue('product_id', $record->product_id);
        }
    }

    protected function setParentValue(string $name, mixed $value): void
    {
        foreach ($this->getContainer()->getFlatFields(withHidden: true) as $field) {
            if ($field->getName() === $name) {
                ($this->makeSetUtility())($name, $value);

                return;
            }
        }
    }
}
