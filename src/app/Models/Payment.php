<?php

namespace App\Models;

use App\Support\OperationsManager;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable(['project_id', 'sales_invoice_id', 'purchase_invoice_id', 'party_id', 'direction', 'reference', 'method', 'amount', 'operation_date', 'description'])]
class Payment extends Model
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

    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }
}
