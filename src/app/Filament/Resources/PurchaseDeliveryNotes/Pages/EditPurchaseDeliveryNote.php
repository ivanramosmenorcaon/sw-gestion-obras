<?php

namespace App\Filament\Resources\PurchaseDeliveryNotes\Pages;

use App\Filament\Actions\CostingActions;
use App\Filament\Resources\PurchaseDeliveryNotes\PurchaseDeliveryNoteResource;
use App\Support\BusinessRecordValidator;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

class EditPurchaseDeliveryNote extends EditRecord
{
    protected static string $resource = PurchaseDeliveryNoteResource::class;

    private ?array $confirmationDraft = null;

    #[Locked]
    public string $linesFingerprint = '';

    #[Locked]
    public string $linesFormBaseline = '';

    protected function afterFill(): void
    {
        $this->rememberLinesState();
    }

    protected function beforeSave(): void
    {
        $this->getRecord()->newQuery()->whereKey($this->getRecord()->id)->lockForUpdate()->firstOrFail();
        if (! hash_equals($this->linesFingerprint, $this->storedLinesFingerprint())) {
            throw ValidationException::withMessages(['data.lines' => 'Las líneas han cambiado desde la relación u otra sesión. Se conservan sus cambios en pantalla. Use «Recargar líneas guardadas» después de revisarlos.']);
        }
    }

    protected function afterSave(): void
    {
        $this->rememberLinesState();
    }

    private function storedLinesFingerprint(): string
    {
        return hash('sha256', $this->getRecord()->lines()->orderBy('id')->get()->toJson());
    }

    private function rememberLinesState(): void
    {
        $this->linesFingerprint = $this->storedLinesFingerprint();
        $this->linesFormBaseline = hash('sha256', serialize($this->data['lines'] ?? []));
    }

    #[On('purchase-delivery-lines-changed')]
    public function syncRelationLines(int $noteId): void
    {
        if ($noteId !== $this->getRecord()->id) {
            return;
        }
        abort_unless(PurchaseDeliveryNoteResource::canEdit($this->getRecord()), 403);
        if (! hash_equals($this->linesFormBaseline, hash('sha256', serialize($this->data['lines'] ?? [])))) {
            Notification::make()->warning()->title('Hay líneas pendientes en el formulario')
                ->body('La relación ha cambiado. Revise su borrador antes de usar «Recargar líneas guardadas»; no se ha descartado ningún cambio.')->persistent()->send();

            return;
        }
        $this->reloadSavedLines();
    }

    public function reloadSavedLines(): void
    {
        abort_unless(PurchaseDeliveryNoteResource::canEdit($this->getRecord()), 403);
        $this->getRecord()->unsetRelation('lines');
        $this->refreshFormData(['lines']);
        $this->rememberLinesState();
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('reloadLines')->label('Recargar líneas guardadas')->requiresConfirmation()
            ->modalDescription('Sustituye solo las líneas del formulario por las guardadas. Copie antes los cambios de líneas que quiera conservar.')
            ->action(fn () => $this->reloadSavedLines()), Action::make('print')->label('Imprimir')->url(fn (): string => route('documents.print', ['type' => 'delivery', 'id' => $this->getRecord()->id]))->openUrlInNewTab(), CostingActions::confirm(PurchaseDeliveryNoteResource::class)->before(fn () => $this->saveBeforeConfirmation()), CostingActions::cancel(PurchaseDeliveryNoteResource::class)];
    }

    private function saveBeforeConfirmation(): void
    {
        $this->confirmationDraft = [$this->data, $this->linesFingerprint, $this->linesFormBaseline];
        $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
    }

    public function restoreConfirmationDraft(): void
    {
        if ($this->confirmationDraft === null) {
            return;
        }
        [$this->data, $this->linesFingerprint, $this->linesFormBaseline] = $this->confirmationDraft;
        $this->getRecord()->unsetRelation('lines');
        $this->form->getFlatFields()['lines']->clearCachedExistingRecords();
        $this->confirmationDraft = null;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return BusinessRecordValidator::inForm(fn (): Model => parent::handleRecordUpdate($record, $data), 'data');
    }
}
