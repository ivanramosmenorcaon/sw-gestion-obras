<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/facturacion/facturas/{salesInvoice}/imprimir', \App\Http\Controllers\SalesInvoicePrintController::class)
    ->middleware('auth')->name('sales-invoices.print');

Route::get('/facturacion/facturas/{salesInvoice}/registro.xml', \App\Http\Controllers\SalesInvoiceXmlController::class)
    ->middleware('auth')->name('sales-invoices.xml');
