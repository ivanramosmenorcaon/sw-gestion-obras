<?php

namespace App\Filament\Resources\PaymentReversals;

use App\Filament\Clusters\Finance\FinanceCluster;
use App\Filament\Resources\BusinessResource;
use App\Filament\Resources\PaymentReversals\Pages\ManagePaymentReversals;
use App\Filament\Schemas\OperationsForms;
use App\Models\PaymentReversal;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PaymentReversalResource extends BusinessResource
{
    protected static ?string $cluster = FinanceCluster::class;

    protected static ?int $navigationSort = 30;

    protected static ?string $model = PaymentReversal::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::ReceiptRefund;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function getModelLabel(): string
    {
        return OperationsForms::LABELS['PaymentReversal'];
    }

    public static function getPluralModelLabel(): string
    {
        return static::getModelLabel();
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Tesorería';
    }

    public static function canEdit(Model $record): bool
    {
        return $record->status === 'draft' && parent::canEdit($record);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return OperationsForms::form($schema, static::getModel());
    }

    public static function table(Table $table): Table
    {
        return OperationsForms::table($table, static::getModel());
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePaymentReversals::route('/'),
        ];
    }
}
