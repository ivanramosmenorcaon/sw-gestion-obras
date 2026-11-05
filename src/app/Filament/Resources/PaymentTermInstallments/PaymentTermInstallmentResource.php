<?php

namespace App\Filament\Resources\PaymentTermInstallments;

use App\Filament\Resources\BusinessResource;
use App\Filament\Resources\PaymentTermInstallments\Pages\CreatePaymentTermInstallment;
use App\Filament\Resources\PaymentTermInstallments\Pages\EditPaymentTermInstallment;
use App\Filament\Resources\PaymentTermInstallments\Pages\ListPaymentTermInstallments;
use App\Filament\Resources\PaymentTermInstallments\Schemas\PaymentTermInstallmentForm;
use App\Filament\Resources\PaymentTermInstallments\Tables\PaymentTermInstallmentsTable;
use App\Models\PaymentTermInstallment;
use App\Support\BusinessRecordValidator;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PaymentTermInstallmentResource extends BusinessResource
{
    protected static ?string $model = PaymentTermInstallment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'sequence';

    protected static bool $shouldRegisterNavigation = false;

    public static function getModelLabel(): string
    {
        return __('business.models.payment_term_installments.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('business.models.payment_term_installments.plural');
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record) && BusinessRecordValidator::canDelete($record);
    }

    public static function form(Schema $schema): Schema
    {
        return PaymentTermInstallmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentTermInstallmentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [

        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentTermInstallments::route('/'),
            'create' => CreatePaymentTermInstallment::route('/create'),
            'edit' => EditPaymentTermInstallment::route('/{record}/edit'),
        ];
    }
}
