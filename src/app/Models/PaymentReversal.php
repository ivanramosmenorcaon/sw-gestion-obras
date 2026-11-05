<?php

namespace App\Models;

use App\Support\OperationsManager;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['project_id', 'reference', 'description', 'amount', 'operation_date', 'payment_id'])]
class PaymentReversal extends Model
{
    use HasFactory;

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'operation_date' => 'date'];
    }

    public function save(array $options = []): bool
    {
        return app(OperationsManager::class)->persist($this, fn (): bool => parent::save($options));
    }

    protected static function booted(): void
    {
        static::saving(fn (self $record) => app(OperationsManager::class)->validate($record));
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }
}
