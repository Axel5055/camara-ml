<?php

namespace App\Services\Exports;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentXlsx
{
    private const FORMATS = ['int' => '#,##0', 'weight' => '#,##0.000', 'money' => '$#,##0.00'];

    /**
     * Stream a real .xlsx. Sections are written, in order, to the sheet each one names; every row of every
     * section is included (no limit), written in streaming mode so large lists stay light on memory.
     *
     * @param  array<string, mixed>  $document
     */
    public function download(array $document): StreamedResponse
    {
        return response()->streamDownload(function () use ($document): void {
            $options = new Options;
            $options->setColumnWidth(34, 1);
            $options->setColumnWidthForRange(20, 2, 10);

            $writer = new Writer($options);
            $writer->openToFile('php://output');

            $bold = (new Style)->withFontBold(true);
            $sheets = [];

            foreach ($document['sections'] as $section) {
                if (! isset($sheets[$section['sheet']])) {
                    $sheet = $sheets === [] ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
                    $sheet->setName($section['sheet']);
                    $sheets[$section['sheet']] = $sheet;

                    if (count($sheets) === 1) {
                        $this->writeHeader($writer, $document, $bold);
                    }
                } else {
                    $writer->setCurrentSheet($sheets[$section['sheet']]);
                    $writer->addRow(Row::fromValues([]));
                }

                $writer->addRow(Row::fromValuesWithStyle([$section['title']], $bold));
                $writer->addRow(Row::fromValuesWithStyle(array_column($section['columns'], 'label'), $bold));

                foreach ($section['rows'] as $row) {
                    $writer->addRow(new Row(array_map(fn ($value, array $column): Cell => $this->cell($value, $column['type']), $row, $section['columns'])));
                }
            }

            $writer->close();
        }, $document['filename'].'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function writeHeader(Writer $writer, array $document, Style $bold): void
    {
        $writer->addRow(Row::fromValuesWithStyle([$document['title']], (new Style)->withFontBold(true)->withFontSize(14)));
        $writer->addRow(Row::fromValues([]));

        foreach ($document['meta'] as [$label, $value]) {
            $writer->addRow(new Row([Cell::fromValue($label, $bold), Cell::fromValue(self::text($value))]));
        }

        $writer->addRow(Row::fromValues([]));

        foreach ($document['totals'] as [$label, $value]) {
            $writer->addRow(new Row([Cell::fromValue($label, $bold), Cell::fromValue(self::text($value))]));
        }

        $writer->addRow(Row::fromValues([]));
    }

    private function cell(mixed $value, string $type): Cell
    {
        if ($value === null) {
            return Cell::fromValue('');
        }

        if ($type === 'text') {
            return Cell::fromValue(self::text($value));
        }

        return Cell::fromValue(
            $type === 'int' ? (int) $value : (float) $value,
            (new Style)->withFormat(self::FORMATS[$type]),
        );
    }

    /**
     * Text cells that start like a formula are neutralized, as in the CSV exports.
     */
    private static function text(mixed $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@'], true) ? "'".$value : $value;
    }
}
