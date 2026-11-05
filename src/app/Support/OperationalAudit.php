<?php

namespace App\Support;

use App\Models\AuditEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

final class OperationalAudit
{
    public static function record(string $event, Model $record): void
    {
        if ($record instanceof AuditEntry || ! Schema::hasTable('audit_entries')) {
            return;
        }
        AuditEntry::create([
            'user_id' => auth()->id(), 'event' => $event, 'subject_type' => $record::class,
            'subject_id' => $record->getKey(),
            'changed_fields' => array_values(array_diff(array_keys($record->getChanges()), ['password', 'remember_token', 'updated_at'])),
        ]);
    }
}
