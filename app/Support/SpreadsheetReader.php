<?php

namespace App\Support;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Minimal dependency-free reader for small .csv / .xlsx roster files.
 * Returns rows as positional arrays of cell strings (column 0, 1, ...).
 * Only what we need for a two-column (name, member_id) upload — not a full
 * spreadsheet engine. Avoids pulling in phpoffice/phpspreadsheet (ext-gd).
 */
class SpreadsheetReader
{
    /** Parse a CSV file into an array of row arrays. */
    public static function csv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException('Unable to open the uploaded file.');
        }

        // Skip a leading UTF-8 BOM if present.
        if (fread($handle, 3) !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $rows = [];
        while (($data = fgetcsv($handle)) !== false) {
            $rows[] = $data;
        }
        fclose($handle);

        return $rows;
    }

    /** Parse the first worksheet of an .xlsx file into an array of row arrays. */
    public static function xlsx(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('The file is not a valid .xlsx workbook.');
        }

        // Shared strings table: most text cells reference this by index.
        $shared = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $sst = @simplexml_load_string($sharedXml);
            if ($sst !== false) {
                foreach ($sst->si as $si) {
                    $shared[] = self::nodeText($si);
                }
            }
        }

        // First worksheet (sheet1.xml, or the lowest-numbered sheet as a fallback).
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml === false) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (is_string($name) && preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                    $sheetXml = $zip->getFromName($name);
                    break;
                }
            }
        }
        $zip->close();

        if ($sheetXml === false) {
            throw new RuntimeException('The workbook has no worksheet.');
        }

        $sheet = @simplexml_load_string($sheetXml);
        if ($sheet === false) {
            throw new RuntimeException('The worksheet could not be parsed.');
        }

        $rows = [];
        foreach ($sheet->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $col = self::columnIndex((string) $c['r']);
                $type = (string) $c['t'];
                if ($type === 's') {
                    $cells[$col] = $shared[(int) $c->v] ?? '';
                } elseif ($type === 'inlineStr') {
                    $cells[$col] = self::nodeText($c->is);
                } else {
                    $cells[$col] = isset($c->v) ? (string) $c->v : '';
                }
            }

            if (empty($cells)) {
                $rows[] = [];
                continue;
            }

            // Re-key into a dense positional array so columns line up.
            $ordered = [];
            $max = max(array_keys($cells));
            for ($i = 0; $i <= $max; $i++) {
                $ordered[$i] = $cells[$i] ?? '';
            }
            $rows[] = $ordered;
        }

        return $rows;
    }

    /** Concatenate the text of an <si>/<is> node, handling rich-text runs. */
    private static function nodeText(SimpleXMLElement $node): string
    {
        if (isset($node->t)) {
            return (string) $node->t;
        }
        $text = '';
        foreach ($node->r as $run) {
            $text .= (string) $run->t;
        }
        return $text;
    }

    /** "B12" -> 1 (zero-based column index). */
    private static function columnIndex(string $ref): int
    {
        $letters = preg_replace('/[^A-Za-z]/', '', $ref);
        if ($letters === '') {
            return 0;
        }
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }
        return $n - 1;
    }
}
