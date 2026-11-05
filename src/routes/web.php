<?php

use App\Http\Controllers\DocumentPrintController;
use App\Http\Controllers\SalesInvoicePrintController;
use App\Http\Controllers\SalesInvoiceXmlController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/facturacion/facturas/{salesInvoice}/imprimir', SalesInvoicePrintController::class)
    ->middleware('auth')->name('sales-invoices.print');

Route::get('/facturacion/facturas/{salesInvoice}/registro.xml', SalesInvoiceXmlController::class)
    ->middleware('auth')->name('sales-invoices.xml');

Route::get('/documentos/{type}/{id}/imprimir', DocumentPrintController::class)
    ->middleware('auth')->whereNumber('id')->name('documents.print');
