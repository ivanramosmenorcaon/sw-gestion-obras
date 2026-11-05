<?php

namespace App\Http\Controllers;

use App\Filament\Resources\ProjectDocuments\ProjectDocumentResource;
use App\Models\ProjectDocument;
use App\Support\OperationalAudit;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectDocumentDownloadController extends Controller
{
    public function __invoke(ProjectDocument $projectDocument): StreamedResponse
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        abort_unless(auth()->user()?->canAccessPanel(Filament::getCurrentPanel()) && ProjectDocumentResource::canView($projectDocument), 403);
        OperationalAudit::record('download', $projectDocument);

        return Storage::disk('local')->download($projectDocument->path);
    }
}
