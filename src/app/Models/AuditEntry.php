<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'event', 'subject_type', 'subject_id', 'changed_fields'])]
class AuditEntry extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['changed_fields' => 'array'];
    }
}
