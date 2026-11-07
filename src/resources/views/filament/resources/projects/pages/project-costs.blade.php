<x-filament-panels::page>
    <x-filament::section heading="Desglose del coste real" description="Importes netos de imputaciones confirmadas. Los borradores no cuentan como gasto.">
        <dl class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @foreach ($categories as $key => $label)
                <div class="rounded-xl bg-gray-50 p-4 dark:bg-white/5">
                    <dt class="text-sm text-gray-600 dark:text-gray-400">{{ $label }}</dt>
                    <dd class="mt-2 text-xl font-semibold text-gray-950 dark:text-white">{{ \App\Support\ProjectCostReport::money($totals[$key]) }}</dd>
                </div>
            @endforeach
        </dl>
    </x-filament::section>

    <x-filament::section heading="Venta y situación económica">
        <dl class="grid gap-4 sm:grid-cols-3">
            <div>
                <dt class="text-sm text-gray-600 dark:text-gray-400">Facturado a cobrar</dt>
                <dd class="mt-2 text-xl font-semibold">{{ \App\Support\ProjectCostReport::money($totals['invoiced']) }}</dd>
            </div>
            <div>
                <dt class="text-sm text-gray-600 dark:text-gray-400">Facturas recibidas: importe a pagar</dt>
                <dd class="mt-2 text-xl font-semibold">{{ \App\Support\ProjectCostReport::money($totals['purchase_payable']) }}</dd>
            </div>
            <div>
                <dt class="text-sm text-gray-600 dark:text-gray-400">Venta aceptada menos coste incurrido</dt>
                <dd class="mt-2 text-xl font-semibold">{{ \App\Support\ProjectCostReport::money($totals['sale_less_actual']) }}</dd>
            </div>
        </dl>
        <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">La venta aceptada se muestra sin impuestos; las facturas y los movimientos de dinero incluyen sus impuestos y retenciones. La diferencia entre venta y coste incurrido no es el margen final: todavía faltan los costes de los trabajos pendientes. Los compromisos nominales pueden incluir gasto ya realizado y no se suman al coste real.</p>
    </x-filament::section>
</x-filament-panels::page>
