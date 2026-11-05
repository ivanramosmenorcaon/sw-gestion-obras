<?php

namespace App\Filament\Resources\PaymentTerms;

use App\Filament\Clusters\Settings\SettingsCluster;
use App\Filament\Resources\BusinessResource;
use App\Filament\Resources\PaymentTerms\Pages\CreatePaymentTerm;
use App\Filament\Resources\PaymentTerms\Pages\EditPaymentTerm;
use App\Filament\Resources\PaymentTerms\Pages\ListPaymentTerms;
use App\Filament\Resources\PaymentTerms\Schemas\PaymentTermForm;
use App\Filament\Resources\PaymentTerms\Tables\PaymentTermsTable;
use App\Models\PaymentTerm;
use App\Support\BusinessRecordValidator;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PaymentTermResource extends BusinessResource
{
    protected static ?string $cluster = SettingsCluster::class;

    protected static ?int $navigationSort = 40;

    protected static ?string $model = PaymentTerm::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::CreditCardPay;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return 'Empresa';
    }

    public static function getModelLabel(): string
    {
        return __('business.models.payment_terms.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('business.models.payment_terms.plural');
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record) && BusinessRecordValidator::canDelete($record);
    }

    public static function form(Schema $schema): Schema
    {
        return PaymentTermForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentTermsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PaymentTermInstallmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentTerms::route('/'),
            'create' => CreatePaymentTerm::route('/create'),
            'edit' => EditPaymentTerm::route('/{record}/edit'),
        ];
    }
}
