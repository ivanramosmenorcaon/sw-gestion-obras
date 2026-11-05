<?php

namespace App\Models;

use App\Support\BudgetEditor;
use App\Support\BusinessRecordValidator;
use Database\Factories\BudgetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable(['project_id', 'source_work_order_id', 'reference', 'name', 'currency_code', 'items', 'offer_date', 'valid_until', 'terms', 'exclusions'])]
class Budget extends Model
{
    /** @use HasFactory<BudgetFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'draft', 'revision' => 1];

    protected static function booted(): void
    {
        static::deleting(function (self $record): void {
            if ($record->fresh()->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Los documentos enviados o aceptados se conservan en el histórico.']);
            }
        });
        static::saving(function (self $record): void {
            if ($record->exists && $record->getRawOriginal('status') !== 'draft' && $record->isDirty(['items', 'name', 'terms', 'exclusions', 'valid_until', 'project_id', 'currency_code'])) {
                throw ValidationException::withMessages(['name' => 'El documento enviado o aceptado es inmutable. Cree una revisión.']);
            }
            BusinessRecordValidator::validate($record);
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['items' => 'array', 'commercial_snapshot' => 'array', 'offer_date' => 'date', 'valid_until' => 'date', 'sent_at' => 'datetime', 'accepted_at' => 'datetime'];
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
