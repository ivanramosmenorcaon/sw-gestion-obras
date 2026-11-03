<?php

namespace App\Http\Controllers;

use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use App\Models\SalesInvoice;
use App\Support\VerifactuXml;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SalesInvoicePrintController extends Controller
{
    public function __invoke(Request $request, SalesInvoice $salesInvoice): View
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        abort_unless($request->user()?->canAccessPanel(Filament::getCurrentPanel()) && SalesInvoiceResource::canView($salesInvoice), 403);
        abort_unless($salesInvoice->status === 'issued', 404);
        $salesInvoice->load('lines', 'rectifiesInvoice');
        $url = VerifactuXml::qrUrl($salesInvoice);
        $qr = (new QRCode(new QROptions(['eccLevel' => EccLevel::M, 'outputBase64' => true])))->render($url);

        return view('billing.invoice', ['invoice' => $salesInvoice, 'qr' => $qr, 'qrUrl' => $url]);
    }
}
