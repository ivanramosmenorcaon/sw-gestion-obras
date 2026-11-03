<?php

namespace App\Models;

use App\Support\BusinessRecordValidator;
use Database\Factories\ProjectChapterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['project_id', 'parent_id', 'code', 'name', 'description', 'position', 'is_active'])]
class ProjectChapter extends Model
{
    /** @use HasFactory<ProjectChapterFactory> */
    use HasFactory;

    protected $table = 'project_chapters';

    protected $attributes = [
        'position' => '1',
        'is_active' => true,
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
            'is_active' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProjectChapter::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ProjectChapter::class, 'parent_id');
    }

    public function workOrderLines(): HasMany
    {
        return $this->hasMany(WorkOrderLine::class, 'project_chapter_id');
    }
}
