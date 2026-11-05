<?php

namespace App\Filament\Resources\PurchaseDeliveryNotes\Schemas;

use App\Filament\Schemas\CostingForms;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Schema;

class PurchaseDeliveryNoteForm
{
    public static function configure(Schema $schema): Schema
    {
        $lineFields = CostingForms::configure(Schema::make(), 'line')->getComponents();
        $lineFields[array_key_last($lineFields)]->extraInputAttributes([
            'x-on:keydown.tab' => "if (!\$event.shiftKey) { const repeater = \$el.closest('[data-keyboard-lines]'); const inputs = [...repeater.querySelectorAll('input:not([type=hidden]):not([disabled])')]; if (inputs.at(-1) === \$el) { \$event.preventDefault(); repeater.querySelector('[data-add-line]').click(); const observer = new MutationObserver(() => { const rows = repeater.querySelectorAll('.fi-fo-repeater-item'); const input = rows[rows.length - 1]?.querySelector('input:not([type=hidden]):not([disabled]),button[role=combobox]'); if (input && input !== \$el && rows.length > count) { input.focus(); observer.disconnect(); } }); const count = repeater.querySelectorAll('.fi-fo-repeater-item').length; observer.observe(repeater, { childList: true, subtree: true }); } }",
        ]);

        return CostingForms::configure($schema, 'note')->components([
            ...CostingForms::configure(Schema::make(), 'note')->getComponents(),
            Repeater::make('lines')->label('Líneas del albarán')->relationship()
                ->schema($lineFields)->columns(4)->columnSpanFull()->defaultItems(1)->reorderable(false)
                ->addActionLabel('Añadir línea')->extraAttributes(['data-keyboard-lines' => true])
                ->addAction(fn (Action $action) => $action->extraAttributes(['data-add-line' => true])),
        ]);
    }
}
