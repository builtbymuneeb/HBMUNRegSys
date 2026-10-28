<?php

namespace App\Support;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Reads the first sheet of a CSV or .xlsx file into rows of strings,
 * enough for a Google Forms / Sheets export without a spreadsheet package.
 */
class Spreadsheet
{
    /**
     * @return list<list<string>>
     */
    public static function rows(string $path, string $extension): array
    {
        return match (strtolower($extension)) {
            'csv', 'txt' => self::csvRows($path),
            'xlsx' => self::xlsxRows($path),
            default => throw new RuntimeException('Upload a .csv or .xlsx file.'),
        };
    }

    /**
     * @return list<list<string>>
     */
    private static function csvRows(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException('The file could not be read.');
        }

        $rows = [];
        while (($row = fgetcsv($handle, escape: '')) !== false) {
            $rows[] = array_map(fn ($cell) => trim((string) $cell), $row);
        }
        fclose($handle);

        if (isset($rows[0][0])) {
            $rows[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', $rows[0][0]);
        }

        return $rows;
    }

    /**
     * @return list<list<string>>
     */
    private static function xlsxRows(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('The file is not a valid .xlsx spreadsheet.');
        }

        try {
            $sharedStrings = self::sharedStrings($zip);
            $sheet = $zip->getFromName(self::firstSheetPath($zip));
            if ($sheet === false) {
                throw new RuntimeException('The spreadsheet has no sheets.');
            }

            $rows = [];
            foreach (simplexml_load_string($sheet)->sheetData->row ?? [] as $row) {
                $cells = [];
                foreach ($row->c as $cell) {
                    $reference = (string) $cell['r'];
                    $cells[$reference === '' ? count($cells) : self::columnIndex($reference)] = trim(self::cellValue($cell, $sharedStrings));
                }

                if ($cells === []) {
                    $rows[] = [];

                    continue;
                }

                $rows[] = array_replace(array_fill(0, max(array_keys($cells)) + 1, ''), $cells);
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    /**
     * @return list<string>
     */
    private static function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $strings = [];
        foreach (simplexml_load_string($xml)->si as $item) {
            $strings[] = self::text($item);
        }

        return $strings;
    }

    private static function firstSheetPath(ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $relations = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbook !== false && $relations !== false) {
            $sheet = simplexml_load_string($workbook)->sheets->sheet[0] ?? null;
            $relationId = $sheet ? (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] : '';

            foreach (simplexml_load_string($relations)->Relationship as $relation) {
                if ((string) $relation['Id'] === $relationId) {
                    return 'xl/'.ltrim(preg_replace('#^/?xl/#', '', (string) $relation['Target']), '/');
                }
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /**
     * @param  list<string>  $sharedStrings
     */
    private static function cellValue(SimpleXMLElement $cell, array $sharedStrings): string
    {
        return match ((string) $cell['t']) {
            's' => $sharedStrings[(int) $cell->v] ?? '',
            'inlineStr' => self::text($cell->is),
            default => (string) $cell->v,
        };
    }

    /**
     * Text of a string item, joining rich-text runs.
     */
    private static function text(SimpleXMLElement $item): string
    {
        if (isset($item->t)) {
            return (string) $item->t;
        }

        $text = '';
        foreach ($item->r as $run) {
            $text .= (string) $run->t;
        }

        return $text;
    }

    /**
     * Zero-based column index of a cell reference, e.g. "C7" → 2.
     */
    private static function columnIndex(string $reference): int
    {
        $index = 0;
        foreach (str_split(preg_replace('/\d+/', '', strtoupper($reference))) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return max($index - 1, 0);
    }
}
