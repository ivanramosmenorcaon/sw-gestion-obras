<?php

namespace App\Filament\Resources\SalesInvoices\Pages;

use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use App\Support\SalesBilling;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSalesInvoice extends EditRecord
{
    protected static string $resource = SalesInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('issue')->label(__('billing.issue'))->requiresConfirmation()->modalDescription(__('billing.messages.issue_confirmation'))
                ->action(function (): void {
                    abort_unless(SalesInvoiceResource::canEdit($this->getRecord()), 403);
                    $this->save(shouldRedirect: false);
                    app(SalesBilling::class)->issue($this->getRecord());
                    Notification::make()->success()->title(__('billing.issued'))->send();
                    $this->redirect(SalesInvoiceResource::getUrl('view', ['record' => $this->getRecord()]));
                }),
            Action::make('cancelDraft')->label(__('billing.cancel'))->color('danger')->requiresConfirmation()->action(function (): void {
                abort_unless(SalesInvoiceResource::canEdit($this->getRecord()), 403);
                app(SalesBilling::class)->cancel($this->getRecord());
                $this->redirect(SalesInvoiceResource::getUrl('index'));
            }),
        ];
    }
}
