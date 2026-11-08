<?php

namespace App\Filament\Actions;

use App\Filament\Resources\ProjectChapters\ProjectChapterResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\WorkOrderLines\WorkOrderLineResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\Project;
use App\Models\WorkOrder;
use App\Support\ArchitectMeasurementSpreadsheet;
use App\Support\Bc3BudgetImporter;
use App\Support\BusinessRecordValidator;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

final class ImportProjectMeasurements
{
    public static function make(Project $project): Action
    {
        return Action::make('importArchitectMeasurements')
            ->label('Importar mediciones (BC3 o Excel)')
            ->authorize(fn (): bool => ProjectResource::canEdit($project)
                && WorkOrderResource::canCreate()
                && WorkOrderLineResource::canCreate()
                && ProjectChapterResource::canCreate())
            ->modalHeading('Importar mediciones (BC3 o Excel)')
            ->modalDescription('Importa y consulta las mediciones originales. Después podrás seleccionar partidas y preparar un presupuesto. El alcance se incorporará a la obra cuando el presupuesto esté aceptado.')
            ->modalSubmitActionLabel('Importar mediciones')
            ->schema([
                TextInput::make('name')->label('Nombre de las mediciones')
                    ->default('Mediciones del arquitecto')->required()->maxLength(255),
                FileUpload::make('file')->label('Archivo de mediciones')
                    ->helperText('BC3 (.bc3) o Excel (.xls o .xlsx) del arquitecto, hasta 5 MB. Se conservarán capítulos, partidas y desgloses de medición. El Excel debe contener el listado desglosado habitual. El BC3 debe usar precios directos en la divisa de la obra, sin ajustes globales de impuestos, bajas ni gastos generales.')
                    ->acceptedFileTypes(['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/x-ole-storage', 'text/plain', 'application/octet-stream'])
                    ->mimeTypeMap([
                        'bc3' => 'text/plain',
                        'xls' => 'application/vnd.ms-excel',
                        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ])
                    ->rules(['extensions:bc3,xls,xlsx'])
                    ->maxSize(5120)->storeFiles(false)->required(),
            ])
            ->action(function (array $data, Component $livewire) use ($project): void {
                $order = BusinessRecordValidator::inForm(
                    function () use ($project, $data): WorkOrder {
                        return DB::transaction(function () use ($project, $data): WorkOrder {
                            $order = app(strtolower($data['file']->getClientOriginalExtension()) === 'bc3' ? Bc3BudgetImporter::class : ArchitectMeasurementSpreadsheet::class)->import($project, $data['file']->getRealPath(), $data['name']);
                            $path = $data['file']->store('measurements/'.$project->id, 'local');
                            if ($path === false) {
                                throw ValidationException::withMessages(['file' => 'No se ha podido archivar el archivo original.']);
                            }
                            try {
                                $order->forceFill(['measurement_source_path' => $path, 'measurement_source_name' => $data['file']->getClientOriginalName()])->save();
                            } catch (\Throwable $exception) {
                                Storage::disk('local')->delete($path);
                                throw $exception;
                            }

                            return $order;
                        });
                    },
                    $livewire->getSchema($livewire->getMountedActionSchemaName())?->getStatePath() ?? '',
                );
                Notification::make()->success()->title('Mediciones importadas')
                    ->body($order->workOrderLines()->count().' partidas disponibles en Mediciones.')
                    ->send();
                $livewire->redirect(WorkOrderResource::getUrl('tree', ['record' => $order]));
            });
    }
}
