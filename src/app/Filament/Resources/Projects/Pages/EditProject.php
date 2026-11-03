<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\ProjectChapters\ProjectChapterResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\WorkOrderLines\WorkOrderLineResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Support\ArchitectMeasurementSpreadsheet;
use App\Support\Bc3BudgetImporter;
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
            Action::make('importBc3Budget')
                ->label('Importar mediciones BC3')
                ->authorize(fn (): bool => ProjectResource::canEdit($this->getRecord())
                    && WorkOrderResource::canCreate()
                    && WorkOrderLineResource::canCreate()
                    && ProjectChapterResource::canCreate())
                ->modalHeading('Importar mediciones BC3')
                ->modalDescription('Se creará una nueva orden de trabajo en borrador con los capítulos, partidas, precios unitarios y mediciones del presupuesto. Los descompuestos se conservarán como referencia dentro de cada partida. Los capítulos con el mismo código se conservarán sin cambios.')
                ->modalSubmitActionLabel('Importar presupuesto')
                ->schema([
                    TextInput::make('name')->label('Nombre de la orden de trabajo')
                        ->default('Presupuesto BC3')->required()->maxLength(255),
                    FileUpload::make('file')->label('Presupuesto BC3')
                        ->helperText('Archivo .bc3 de hasta 5 MB. Precios unitarios directos en la divisa de la obra. No se aplican impuestos, bajas ni gastos generales globales; los archivos que los incluyan se rechazarán.')
                        ->acceptedFileTypes(['text/plain', 'application/octet-stream'])
                        ->mimeTypeMap(['bc3' => 'text/plain'])
                        ->rules(['extensions:bc3'])
                        ->maxSize(5120)->storeFiles(false)->required(),
                ])
                ->action(function (array $data): void {
                    $order = BusinessRecordValidator::inForm(
                        fn () => app(Bc3BudgetImporter::class)->import($this->getRecord(), $data['file']->getRealPath(), $data['name']),
                        $this->getMountedActionSchema()?->getStatePath() ?? '',
                    );
                    Notification::make()->success()->title('Presupuesto BC3 importado')
                        ->body($order->workOrderLines()->count().' partidas creadas en una orden de trabajo en borrador.')
                        ->send();
                    $this->redirect(WorkOrderResource::getUrl('edit', ['record' => $order]));
                }),
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
