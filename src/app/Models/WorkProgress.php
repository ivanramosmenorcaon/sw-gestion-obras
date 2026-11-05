<?php

namespace App\Models;

use App\Support\OperationsManager;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable(['project_id', 'work_order_line_id', 'reference', 'description', 'quantity', 'operation_date'])]
class WorkProgress extends Model
{
    use HasFactory;

    protected $table = 'work_progress';

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:6', 'operation_date' => 'date'];
    }

    public function save(array $options = []): bool
    {
        return app(OperationsManager::class)->persist($this, fn (): bool => parent::save($options));
    }

    protected static function booted(): void
    {
        static::saving(fn (self $record) => app(OperationsManager::class)->validate($record));
        static::deleting(function (self $record): void {
            if ($record->fresh()?->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Un registro confirmado se conserva. Registre una corrección trazable.']);
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function workOrderLine(): BelongsTo
    {
        return $this->belongsTo(WorkOrderLine::class, 'work_order_line_id');
    }
}
