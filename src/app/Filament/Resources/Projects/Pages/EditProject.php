<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\ProjectChapters\ProjectChapterResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\WorkOrderLines\WorkOrderLineResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Support\ArchitectMeasurementSpreadsheet;
use App\Support\BusinessRecordValidator;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importArchitectMeasurements')
                ->label('Importar mediciones')
                ->authorize(fn (): bool => ProjectResource::canEdit($this->getRecord())
                    && WorkOrderResource::canCreate()
                    && WorkOrderLineResource::canCreate()
                    && ProjectChapterResource::canCreate())
                ->modalHeading('Importar mediciones del arquitecto')
                ->modalDescription('Se creará una orden de trabajo en borrador con todas las partidas, sus capítulos y desgloses de medición. Los capítulos existentes se conservarán. Un documento ya importado en esta obra no se duplicará.')
                ->modalSubmitActionLabel('Crear partidas')
                ->schema([
                    TextInput::make('name')->label('Nombre de la orden de trabajo')
                        ->default('Mediciones del arquitecto')->required()->maxLength(255),
                    FileUpload::make('file')->label('Excel de mediciones')
                        ->helperText('Listado desglosado con columnas: código, unidad, descripción, unidades, largo, ancho, alto, parcial, medición, precio unitario e importe. Archivo .xls o .xlsx de hasta 5 MB.')
                        ->acceptedFileTypes(['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/x-ole-storage'])
                        ->mimeTypeMap([
                            'xls' => 'application/vnd.ms-excel',
                            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->maxSize(5120)->storeFiles(false)->required(),
                ])
                ->action(function (array $data): void {
                    $order = BusinessRecordValidator::inForm(
                        fn () => app(ArchitectMeasurementSpreadsheet::class)->import($this->getRecord(), $data['file']->getRealPath(), $data['name']),
                        $this->getMountedActionSchema()?->getStatePath() ?? '',
                    );
                    Notification::make()->success()->title('Mediciones importadas')
                        ->body($order->workOrderLines()->count().' partidas creadas en una orden de trabajo en borrador.')
                        ->send();
                    $this->redirect(WorkOrderResource::getUrl('edit', ['record' => $order]));
                }),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordUpdate($record, $data), 'data');
    }
}
