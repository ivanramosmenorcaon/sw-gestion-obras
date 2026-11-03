<?php

namespace App\Models;

use App\Support\BudgetEditor;
use App\Support\BusinessRecordValidator;
use Database\Factories\BudgetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['project_id', 'source_work_order_id', 'reference', 'name', 'currency_code', 'items'])]
class Budget extends Model
{
    /** @use HasFactory<BudgetFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (self $record): void {
            BusinessRecordValidator::validate($record);
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['items' => 'array'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function sourceWorkOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'source_work_order_id');
    }

    public function total(): string
    {
        return BudgetEditor::total($this->items ?? []);
    }
}
