<?php

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
