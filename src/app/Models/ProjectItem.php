<?php

namespace App\Models;

use App\Support\BusinessRecordValidator;
use App\Support\ProjectScope;
use Database\Factories\ProjectItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Validation\ValidationException;

#[Fillable(['project_id', 'project_chapter_id', 'position', 'source_code', 'type', 'product_id', 'product_unit_id', 'description', 'unit_id', 'unit_label', 'base_unit_id', 'base_unit_factor', 'planned_quantity', 'estimated_unit_cost', 'estimated_unit_sale_price', 'discount_percentage', 'measurement_details', 'budget_breakdown', 'notes'])]
class ProjectItem extends Model
{
    /** @use HasFactory<ProjectItemFactory> */
    use HasFactory;

    protected $attributes = ['position' => 1, 'type' => 'service', 'base_unit_factor' => '1', 'planned_quantity' => '0', 'estimated_unit_cost' => '0', 'estimated_unit_sale_price' => '0', 'discount_percentage' => '0'];

    protected function casts(): array
    {
        return ['planned_quantity' => 'decimal:6', 'base_unit_factor' => 'decimal:12', 'estimated_unit_cost' => 'decimal:6', 'estimated_unit_sale_price' => 'decimal:6', 'discount_percentage' => 'decimal:4', 'measurement_details' => 'array', 'budget_breakdown' => 'array'];
    }

    public function save(array $options = []): bool
    {
        return $this->getConnection()->transaction(function () use ($options): bool {
            if ($this->exists) {
                $stored = self::whereKey($this->id)->lockForUpdate()->firstOrFail();
                $promotion = ! $stored->source_budget_id && $this->source_budget_id && $this->sourceBudget?->status === 'accepted'
                    && $this->sourceBudget->project_id === $stored->project_id
                    && ! $stored->workOrderLines()->whereHas('workOrder', fn ($q) => $q->where('purpose', 'intervention')->where('status', '!=', 'cancelled'))->exists();
                if ($this->isDirty() && ! $promotion && ($stored->source_budget_id || $stored->workOrderLines()->exists())) {
                    throw ValidationException::withMessages(['description' => 'La partida ya está contratada o asignada. Conserve el acuerdo y tramite una revisión o un extra.']);
                }
            }
            BusinessRecordValidator::validate($this);

            return parent::save($options);
        }, 3);
    }

    protected static function booted(): void
    {
        static::deleting(function (self $item): void {
            if ($item->fresh()->source_budget_id || $item->workOrderLines()->exists()) {
                throw ValidationException::withMessages(['description' => 'La partida contratada o asignada se conserva en la obra.']);
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function projectChapter(): BelongsTo
    {
        return $this->belongsTo(ProjectChapter::class);
    }

    public function sourceBudget(): BelongsTo
    {
        return $this->belongsTo(Budget::class, 'source_budget_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }

    public function workOrderLines(): HasMany
    {
        return $this->hasMany(WorkOrderLine::class);
    }

    public function progress(): HasManyThrough
    {
        return $this->hasManyThrough(WorkProgress::class, WorkOrderLine::class, 'project_item_id', 'work_order_line_id');
    }

    public function invoiceLines(): HasManyThrough
    {
        return $this->hasManyThrough(SalesInvoiceLine::class, WorkOrderLine::class, 'project_item_id', 'work_order_line_id');
    }

    public function remainingQuantity(): string
    {
        return app(ProjectScope::class)->remaining($this);
    }
}
