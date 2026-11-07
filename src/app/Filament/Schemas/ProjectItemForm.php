<?php

namespace App\Filament\Schemas;

use App\Filament\Resources\WorkOrderLines\Schemas\WorkOrderLineForm;
use Filament\Forms\Components\Field;
use Filament\Schemas\Schema;

class ProjectItemForm
{
    public static function configure(Schema $schema, array $context = []): Schema
    {
        $schema = WorkOrderLineForm::configure($schema, $context);

        foreach ($schema->getComponents() as $field) {
            if ($field->getName() === 'position') {
                $field->default(1);
            }
            if ($field->getName() === 'type') {
                $field->default('service');
            }
        }

        return $schema->components(array_values(array_filter($schema->getComponents(), fn (Field $field): bool => ! in_array($field->getName(), ['work_order_id', 'work_order_phase_id', 'project_item_id'], true))));
    }
}
