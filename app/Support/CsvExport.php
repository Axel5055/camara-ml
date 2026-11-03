<?php

namespace App\Support;

use Closure;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CsvExport
{
    /**
     * Stream a UTF-8 CSV (with BOM so Excel opens accents correctly). Cells that a spreadsheet could
     * interpret as formulas are neutralized.
     *
     * @param  list<string>  $headers
     * @param  iterable<mixed>  $rows
     * @param  Closure(mixed): list<scalar|null>  $map  turns one source row into the list of cells
     */
    public static function download(string $filename, array $headers, iterable $rows, Closure $map): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows, $map): void {
            $out = fopen('php://output', 'w') ?: throw new RuntimeException('No se pudo abrir la salida del archivo.');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);

            foreach ($rows as $row) {
                fputcsv($out, array_map(self::safe(...), $map($row)));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public static function safe(mixed $value): string|int|float|null
    {
        if (is_string($value) && $value !== '' && ! is_numeric($value) && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return is_bool($value) ? (int) $value : $value;
    }
}
