<?php

namespace App\Models;

use App\Support\BusinessRecordValidator;
use App\Support\ProjectScope;
use Database\Factories\WorkOrderLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['project_item_id', 'discount_percentage', 'project_id', 'work_order_id', 'project_chapter_id', 'work_order_phase_id', 'position', 'type', 'product_id', 'product_unit_id', 'description', 'unit_id', 'unit_label', 'base_unit_id', 'base_unit_factor', 'planned_quantity', 'estimated_unit_cost', 'estimated_unit_sale_price', 'notes', 'source_code', 'measurement_details', 'budget_breakdown'])]
class WorkOrderLine extends Model
{
    /** @use HasFactory<WorkOrderLineFactory> */
    use HasFactory;

    public function save(array $options = []): bool
    {
        return $this->getConnection()->transaction(function () use ($options): bool {
            if ($this->exists) {
                $storedItemId = self::whereKey($this->id)->value('project_item_id');
                if ($storedItemId !== null && (int) $storedItemId !== (int) $this->project_item_id) {
                    throw ValidationException::withMessages(['project_item_id' => 'La asignación conserva su partida de obra de origen.']);
                }
            }
            if ($this->project_item_id) {
                app(ProjectScope::class)->prepareAllocation($this);
            }

            return parent::save($options);
        }, 3);
    }

    public function projectItem(): BelongsTo
    {
        return $this->belongsTo(ProjectItem::class);
    }

    protected $table = 'work_order_lines';

    protected $attributes = [
        'discount_percentage' => '0',
        'base_unit_factor' => '1',
        'planned_quantity' => '0',
        'estimated_unit_cost' => '0',
        'estimated_unit_sale_price' => '0',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $record): void {
            BusinessRecordValidator::validate($record);
        });
        static::deleting(function (self $record): void {
            if (! BusinessRecordValidator::canDelete($record)) {
                throw ValidationException::withMessages(['id' => __('business.messages.referenced')]);
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'discount_percentage' => 'decimal:4',
            'base_unit_factor' => 'decimal:12',
            'planned_quantity' => 'decimal:6',
            'measurement_details' => 'array',
            'budget_breakdown' => 'array',
            'estimated_unit_cost' => 'decimal:6',
            'estimated_unit_sale_price' => 'decimal:6',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
    }

    public function projectChapter(): BelongsTo
    {
        return $this->belongsTo(ProjectChapter::class, 'project_chapter_id');
    }

    public function workOrderPhase(): BelongsTo
    {
        return $this->belongsTo(WorkOrderPhase::class, 'work_order_phase_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'product_unit_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function salesInvoiceLines(): HasMany
    {
        return $this->hasMany(SalesInvoiceLine::class, 'work_order_line_id');
    }
}
