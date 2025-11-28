<?php

namespace Apphp\PrettyPrint;

class Formatter
{
    /**
     * Format a value as a number when possible.
     *
     * Integers are returned verbatim; floats are rendered with 4 decimal places;
     * non-numeric values are cast to string.
     *
     * @param mixed $v
     * @param int $precision
     * @return string
     */
    public static function formatNumber(mixed $v, int $precision = 2): string
    {
        if (is_int($v)) {
            return (string)$v;
        }
        if (is_float($v)) {
            return number_format($v, $precision, '.', '');
        }
        return (string)$v;
    }

    /**
     * Format a 2D matrix with aligned columns.
     *
     * @param array $matrix 2D array.
     * @param int $precision Number of decimal places to use for floats.
     * @return string
     */
    public static function format2DAligned(array $matrix, int $precision = 2): string
    {
        $cols = 0;
        foreach ($matrix as $row) {
            if (is_array($row)) {
                $cols = max($cols, count($row));
            }
        }
        if ($cols === 0) {
            return '[]';
        }

        // Pre-format all cells (numbers and strings) and compute widths in one pass
        $widths = array_fill(0, $cols, 0);
        $formatted = [];
        foreach ($matrix as $r => $row) {
            $frow = [];
            for ($c = 0; $c < $cols; $c++) {
                $s = '';
                if (array_key_exists($c, $row)) {
                    $cell = $row[$c];
                    if (is_int($cell) || is_float($cell)) {
                        $s = self::formatNumber($cell, $precision);
                    } elseif (is_string($cell)) {
                        $s = "'" . addslashes($cell) . "'";
                    } elseif (is_bool($cell)) {
                        $s = $cell ? 'True' : 'False';
                    } elseif (is_null($cell)) {
                        $s = 'None';
                    } elseif (is_object($cell)) {
                        $s = 'Object';
                    } elseif (is_array($cell)) {
                        $s = 'Array';
                    } else {
                        $s = 'Unknown';
                    }
                }
                $frow[$c] = $s;
                $widths[$c] = max($widths[$c], strlen($s));
            }
            $formatted[$r] = $frow;
        }

        // Build lines using precomputed widths
        $lines = [];
        foreach ($formatted as $frow) {
            $cells = [];
            for ($c = 0; $c < $cols; $c++) {
                $cells[] = str_pad($frow[$c] ?? '', $widths[$c], ' ', STR_PAD_LEFT);
            }
            $lines[] = '[' . implode(', ', $cells) . ']';
        }

        if (count($lines) === 1) {
            return '[' . $lines[0] . ']';
        }
        return '[' . implode(",\n ", $lines) . ']';
    }
}
