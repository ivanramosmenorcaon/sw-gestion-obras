<?php

namespace App\Models;

use App\Support\OperationsManager;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable(['project_id', 'contractor_id', 'reference', 'description', 'amount', 'retention_percentage', 'starts_on', 'ends_on'])]
class ContractorAgreement extends Model
{
    use HasFactory;

    protected $attributes = ['status' => 'draft', 'retention_percentage' => '0'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'retention_percentage' => 'decimal:4', 'starts_on' => 'date', 'ends_on' => 'date'];
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

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'contractor_id');
    }
}
