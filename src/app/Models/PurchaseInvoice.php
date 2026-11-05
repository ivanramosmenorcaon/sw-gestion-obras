<?php

namespace App\Models;

use App\Support\OperationsManager;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['project_id', 'supplier_id', 'contractor_agreement_id', 'purchase_delivery_note_id', 'purchase_order_id', 'reference', 'description', 'operation_date', 'due_date', 'net_amount', 'tax_amount', 'withholding_amount', 'retention_amount'])]
class PurchaseInvoice extends Model
{
    use HasFactory;

    protected $attributes = ['status' => 'draft', 'net_amount' => '0', 'tax_amount' => '0', 'withholding_amount' => '0', 'retention_amount' => '0'];

    protected function casts(): array
    {
        return ['net_amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'withholding_amount' => 'decimal:2', 'retention_amount' => 'decimal:2', 'payable_total' => 'decimal:2', 'operation_date' => 'date', 'due_date' => 'date'];
    }

    public function save(array $options = []): bool
    {
        return app(OperationsManager::class)->persist($this, fn (): bool => parent::save($options));
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceLine::class);
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

    public function contractorAgreement(): BelongsTo
    {
        return $this->belongsTo(ContractorAgreement::class, 'contractor_agreement_id');
    }

    public function purchaseDeliveryNote(): BelongsTo
    {
        return $this->belongsTo(PurchaseDeliveryNote::class, 'purchase_delivery_note_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }
}
