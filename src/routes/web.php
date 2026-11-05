<?php

use App\Http\Controllers\DocumentPrintController;
use App\Http\Controllers\OperationsExportController;
use App\Http\Controllers\ProjectDocumentDownloadController;
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

Route::get('/obras/documentos/{projectDocument}/descargar', ProjectDocumentDownloadController::class)
    ->middleware('auth')->name('project-documents.download');

Route::get('/operaciones/exportar/{type}', OperationsExportController::class)
    ->middleware('auth')->name('operations.export');
