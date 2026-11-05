<?php

namespace App\Filament\Resources;

use App\Support\OperationalAccess;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class BusinessResource extends Resource
{
    public static function getEloquentQuery(): Builder
    {
        return OperationalAccess::scope(parent::getEloquentQuery());
    }

    public static function canViewAny(): bool
    {
        return OperationalAccess::allows(static::getModel(), 'viewAny') && parent::canViewAny();
    }

    public static function canCreate(): bool
    {
        return OperationalAccess::allows(static::getModel(), 'create') && parent::canCreate();
    }

    public static function canView(Model $record): bool
    {
        return OperationalAccess::allows(static::getModel(), 'view', $record) && parent::canView($record);
    }

    public static function canEdit(Model $record): bool
    {
        return OperationalAccess::allows(static::getModel(), 'update', $record) && parent::canEdit($record);
    }

    public static function canDelete(Model $record): bool
    {
        return OperationalAccess::allows(static::getModel(), 'delete', $record) && parent::canDelete($record);
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->role === 'administrator' && parent::canDeleteAny();
    }
}
