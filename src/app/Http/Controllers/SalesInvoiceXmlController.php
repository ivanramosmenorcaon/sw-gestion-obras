<?php

namespace App\Http\Controllers;

use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use App\Models\SalesInvoice;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SalesInvoiceXmlController extends Controller
{
    public function __invoke(Request $request, SalesInvoice $salesInvoice): Response
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        abort_unless($request->user()?->canAccessPanel(Filament::getCurrentPanel()) && SalesInvoiceResource::canView($salesInvoice), 403);
        abort_unless($salesInvoice->status === 'issued', 404);
        return response($salesInvoice->billingRecord->xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="registro-'.$salesInvoice->id.'.xml"', 'Cache-Control' => 'private, no-store']);
    }
}
