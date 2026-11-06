<?php

namespace App\Http\Controllers;

use App\Models\Entry;
use App\Models\StockExit;
use App\Models\Transfer;
use App\Services\Exports\DocumentPdf;
use App\Services\Exports\DocumentXlsx;
use App\Services\Exports\EntryReport;
use App\Services\Exports\ExitReport;
use App\Services\Exports\TransferReport;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecordExportController extends Controller
{
    public function __construct(private DocumentPdf $pdf, private DocumentXlsx $xlsx) {}

    public function entry(Entry $entry, string $format, EntryReport $report): StreamedResponse
    {
        Gate::authorize('view', $entry);

        return $this->respond($report->build($entry, Gate::allows('view-costs')), $format);
    }

    public function exit(StockExit $exit, string $format, ExitReport $report): StreamedResponse
    {
        Gate::authorize('view', $exit);

        return $this->respond($report->build($exit, Gate::allows('view-costs')), $format);
    }

    public function transfer(Transfer $transfer, string $format, TransferReport $report): StreamedResponse
    {
        Gate::authorize('view', $transfer);

        return $this->respond($report->build($transfer), $format);
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function respond(array $document, string $format): StreamedResponse
    {
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 404);

        return $format === 'pdf' ? $this->pdf->download($document) : $this->xlsx->download($document);
    }
}
