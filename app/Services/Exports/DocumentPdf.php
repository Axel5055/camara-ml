<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentPdf
{
    /** Longest list printed in the PDF; the Excel copy always has every row. */
    public const ROW_LIMIT = 500;

    /**
     * @param  array<string, mixed>  $document
     */
    public function download(array $document): StreamedResponse
    {
        $sections = array_map(function (array $section): array {
            $section['shown'] = $section['rows']->take(self::ROW_LIMIT)->all();

            return $section;
        }, $document['sections']);

        $output = Pdf::loadView('exports.detail', [
            'document' => $document,
            'sections' => $sections,
            'limit' => self::ROW_LIMIT,
            'generatedAt' => now()->format('d/m/Y H:i'),
            'logo' => 'data:image/png;base64,'.base64_encode((string) file_get_contents(public_path('images/logo-pdf.png'))),
        ])->setPaper('a4', 'landscape')->output();

        return response()->streamDownload(function () use ($output): void {
            echo $output;
        }, $document['filename'].'.pdf', ['Content-Type' => 'application/pdf']);
    }
}
