<?php

namespace App\Filament\Actions;

use App\Models\MaterialConsumption;
use App\Models\PurchaseDeliveryNote;
use App\Models\WorkTimeEntry;
use App\Support\ProjectCosting;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

final class CostingActions
{
    /** @param class-string $resource */
    public static function confirm(string $resource): Action
    {
        return Action::make('confirm')->label(fn (Model $record): string => $record instanceof PurchaseDeliveryNote ? __('costing.receive') : __('costing.post'))->requiresConfirmation()
            ->authorize(fn (Model $record): bool => $record->status === 'draft' && $resource::canEdit($record))
            ->action(function (Model $record, Component $livewire) use ($resource): void {
                abort_unless($resource::canEdit($record), 403);
                try {
                    $service = app(ProjectCosting::class);
                    match (true) {
                        $record instanceof PurchaseDeliveryNote => $service->receive($record), $record instanceof MaterialConsumption => $service->postMaterial($record), $record instanceof WorkTimeEntry => $service->postTime($record)
                    };
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title(__('costing.failed'))->body(implode(' ', Arr::flatten($exception->errors())))->persistent()->send();
                    throw $exception;
                }
                Notification::make()->success()->title(__('costing.posted'))->send();
                $livewire->redirect($resource::getUrl('view', ['record' => $record]));
            });
    }

    /** @param class-string $resource */
    public static function cancel(string $resource): Action
    {
        return Action::make('cancelDraft')->label(__('costing.cancel'))->color('danger')->requiresConfirmation()
            ->authorize(fn (Model $record): bool => $record->status === 'draft' && $resource::canEdit($record))
            ->action(function (Model $record, Component $livewire) use ($resource): void {
                abort_unless($resource::canEdit($record), 403);
                app(ProjectCosting::class)->cancel($record);
                $livewire->redirect($resource::getUrl('view', ['record' => $record]));
            });
    }
}
