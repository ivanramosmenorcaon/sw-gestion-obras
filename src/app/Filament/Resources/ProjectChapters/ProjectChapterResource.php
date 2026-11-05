<?php

namespace App\Filament\Resources\ProjectChapters;

use App\Filament\Resources\BusinessResource;
use App\Filament\Resources\ProjectChapters\Pages\CreateProjectChapter;
use App\Filament\Resources\ProjectChapters\Pages\EditProjectChapter;
use App\Filament\Resources\ProjectChapters\Pages\ListProjectChapters;
use App\Filament\Resources\ProjectChapters\Schemas\ProjectChapterForm;
use App\Filament\Resources\ProjectChapters\Tables\ProjectChaptersTable;
use App\Models\ProjectChapter;
use App\Support\BusinessRecordValidator;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProjectChapterResource extends BusinessResource
{
    protected static ?string $model = ProjectChapter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $shouldRegisterNavigation = false;

    public static function getModelLabel(): string
    {
        return __('business.models.project_chapters.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('business.models.project_chapters.plural');
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record) && BusinessRecordValidator::canDelete($record);
    }

    public static function form(Schema $schema): Schema
    {
        return ProjectChapterForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProjectChaptersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [

        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjectChapters::route('/'),
            'create' => CreateProjectChapter::route('/create'),
            'edit' => EditProjectChapter::route('/{record}/edit'),
        ];
    }
}
