<?php

namespace App\Models;

use App\Support\OperationsManager;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['project_id', 'supplier_id', 'reference', 'description', 'amount', 'operation_date', 'due_date'])]
class PurchaseOrder extends Model
{
    use HasFactory;

    protected $attributes = ['status' => 'draft', 'amount' => '0'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'operation_date' => 'date', 'due_date' => 'date'];
    }

    public function save(array $options = []): bool
    {
        return app(OperationsManager::class)->persist($this, fn (): bool => parent::save($options));
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'supplier_id');
    }
}
