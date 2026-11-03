<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\ProjectChapters\ProjectChapterResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\WorkOrderLines\WorkOrderLineResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Support\ArchitectMeasurementSpreadsheet;
use App\Support\Bc3BudgetImporter;
use App\Support\BudgetEditor;
use App\Support\BusinessRecordValidator;
use App\Support\PdfBudgetImporter;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Database\Eloquent\Model;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    /** @var array{chapters: int, items: int, pages: int, total: string, chapter_names: string}|null */
    public ?array $pdfImportPreview = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importPdfBudget')
                ->label('Importar presupuesto PDF')
                ->authorize(fn (): bool => ProjectResource::canEdit($this->getRecord())
                    && WorkOrderResource::canCreate()
                    && WorkOrderLineResource::canCreate()
                    && ProjectChapterResource::canCreate())
                ->modalHeading('Importar presupuesto PDF')
                ->modalDescription('Se crearán los capítulos que falten y una orden de trabajo en borrador con sus partidas, cantidades y precios sin impuestos. Se comprobarán los totales antes de guardar. Las órdenes existentes no se modificarán.')
                ->modalSubmitActionLabel('Importar presupuesto')
                ->steps([
                    Step::make('Archivo')->schema([
                        TextInput::make('name')->label('Nombre de la orden de trabajo')
                            ->default('Presupuesto PDF')->required()->maxLength(255),
                        FileUpload::make('file')->label('Presupuesto en PDF')
                            ->helperText('PDF con texto seleccionable, hasta 10 MB y 100 páginas. Admite los diseños de listado de medición y presupuesto y presupuesto y mediciones de los ejemplos. No admite documentos escaneados.')
                            ->acceptedFileTypes(['application/pdf'])->mimeTypeMap(['pdf' => 'application/pdf'])
                            ->maxSize(10240)->storeFiles(false)->required()
                            ->afterStateUpdated(function (): void {
                                $this->pdfImportPreview = null;
                            }),
                    ])->afterValidation(function (Get $get): void {
                        $state = $get('file');
                        $file = $state instanceof TemporaryUploadedFile ? $state : collect(is_array($state) ? $state : [])->first(fn (mixed $value): bool => $value instanceof TemporaryUploadedFile);
                        abort_unless($file instanceof TemporaryUploadedFile, 422);
                        $document = BusinessRecordValidator::inForm(
                            fn (): array => app(PdfBudgetImporter::class)->inspect($file->getRealPath()),
                            $this->getMountedActionSchema()?->getStatePath() ?? '',
                        );
                        $this->pdfImportPreview = [
                            'chapters' => count($document['chapters']), 'items' => count($document['items']),
                            'pages' => $document['page_count'], 'total' => BudgetEditor::formatAmount($document['net_total']).' EUR',
                            'chapter_names' => implode(' · ', array_map(fn (array $chapter): string => $chapter['code'].' '.$chapter['name'], $document['chapters'])),
                        ];
                    }),
                    Step::make('Revisar')->schema([
                        TextEntry::make('detected_chapters')->label('Capítulos')->state(fn (): int|string => $this->pdfImportPreview['chapters'] ?? '—'),
                        TextEntry::make('detected_items')->label('Partidas')->state(fn (): int|string => $this->pdfImportPreview['items'] ?? '—'),
                        TextEntry::make('detected_total')->label('Total sin impuestos')->state(fn (): string => $this->pdfImportPreview['total'] ?? '—'),
                        TextEntry::make('detected_names')->label('Capítulos detectados')->state(fn (): string => $this->pdfImportPreview['chapter_names'] ?? '—'),
                    ]),
                ])
                ->action(function (array $data): void {
                    $order = BusinessRecordValidator::inForm(
                        fn () => app(PdfBudgetImporter::class)->import($this->getRecord(), $data['file']->getRealPath(), $data['name']),
                        $this->getMountedActionSchema()?->getStatePath() ?? '',
                    );
                    Notification::make()->success()->title('Presupuesto PDF importado')
                        ->body($order->workOrderLines()->count().' partidas creadas. Revise los capítulos y precios en el árbol de la orden.')
                        ->send();
                    $this->redirect(WorkOrderResource::getUrl('tree', ['record' => $order]));
                }),
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
