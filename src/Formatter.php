<?php

namespace Apphp\PrettyPrint;

/**
 * Formatter for pretty-printing arrays.
 */
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
                    $s = self::formatCell($cell, $precision);
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

    /**
     * Format a 2D matrix showing head/tail rows and columns with ellipses in-between.
     *
     * @param array $matrix 2D array of ints/floats.
     * @param int $headRows Number of head rows to display.
     * @param int $tailRows Number of tail rows to display.
     * @param int $headCols Number of head columns to display.
     * @param int $tailCols Number of tail columns to display.
     * @param int $precision Number of decimal places to use for floats.
     * @return string
     */
    public static function format2DSummarized(array $matrix, int $headRows = 5, int $tailRows = 5, int $headCols = 5, int $tailCols = 5, int $precision = 2): string
    {
        $rows = count($matrix);
        $cols = 0;
        foreach ($matrix as $row) {
            if (is_array($row)) {
                $cols = max($cols, count($row));
            }
        }

        $rowIdxs = [];
        if ($rows <= $headRows + $tailRows) {
            for ($r = 0; $r < $rows; $r++) {
                $rowIdxs[] = $r;
            }
        } else {
            for ($r = 0; $r < $headRows; $r++) {
                $rowIdxs[] = $r;
            }
            for ($r = $rows - $tailRows; $r < $rows; $r++) {
                $rowIdxs[] = $r;
            }
        }

        $colPositions = [];
        if ($cols <= $headCols + $tailCols) {
            for ($c = 0; $c < $cols; $c++) {
                $colPositions[] = $c;
            }
        } else {
            for ($c = 0; $c < $headCols; $c++) {
                $colPositions[] = $c;
            }
            $colPositions[] = '...';
            for ($c = $cols - $tailCols; $c < $cols; $c++) {
                $colPositions[] = $c;
            }
        }

        // Pre-format selected cells and compute widths in one pass (support numbers and strings)
        $widths = array_fill(0, count($colPositions), 0);
        $formatted = [];
        foreach ($rowIdxs as $rIndex) {
            $frow = [];
            foreach ($colPositions as $i => $pos) {
                $s = '';
                if ($pos === '...') {
                    $s = '...';
                } elseif (array_key_exists($pos, $matrix[$rIndex])) {
                    $cell = $matrix[$rIndex][$pos];
                    $s = self::formatCell($cell, $precision);
                }
                $frow[$i] = $s;
                $widths[$i] = max($widths[$i], strlen($s));
            }
            $formatted[] = $frow;
        }
        foreach ($colPositions as $i => $pos) {
            if ($pos === '...') {
                $widths[$i] = max($widths[$i], 3);
            }
        }

        // Build lines from pre-formatted rows
        $buildRow = function (array $frow) use ($widths) {
            $cells = [];
            foreach ($frow as $i => $s) {
                $cells[] = str_pad($s, $widths[$i], ' ', STR_PAD_LEFT);
            }
            // Add extra space to align columns like earlier tweak
            return ' [' . implode(', ', $cells) . ']';
        };

        $lines = [];
        $headCount = ($rows <= $headRows + $tailRows) ? count($rowIdxs) : $headRows;
        for ($i = 0; $i < $headCount; $i++) {
            $lines[] = $buildRow($formatted[$i]);
        }
        if ($rows > $headRows + $tailRows) {
            $lines[] = ' ...';
        }
        if ($rows > $headRows + $tailRows) {
            $total = count($formatted);
            for ($i = $headCount; $i < $total; $i++) {
                $lines[] = $buildRow($formatted[$i]);
            }
        }

        if (count($lines) === 1) {
            return '[' . $lines[0] . ']';
        }
        return '[' . trim(implode(",\n ", $lines)) . ']';
    }

    /**
     * Format a 2D numeric matrix in a PyTorch-like representation with summarization.
     *
     * @param array $matrix 2D array of ints/floats.
     * @param int $headRows Number of head rows to display.
     * @param int $tailRows Number of tail rows to display.
     * @param int $headCols Number of head columns to display.
     * @param int $tailCols Number of tail columns to display.
     * @param string $label Prefix label used instead of "tensor".
     * @param int $precision Number of decimal places to use for floats.
     * @return string
     */
    public static function format2DTorch(array $matrix, int $headRows = 5, int $tailRows = 5, int $headCols = 5, int $tailCols = 5, string $label = 'tensor', int $precision = 2): string
    {
        $s = Formatter::format2DSummarized($matrix, $headRows, $tailRows, $headCols, $tailCols, $precision);
        // Replace the very first '[' with 'tensor([['
        if (strlen($s) > 0 && $s[0] === '[') {
            $s = $label . "([\n  " . substr($s, 1);
        }
        // Indent subsequent lines by one extra space to align under the double braket
        $s = str_replace("\n ", "\n  ", $s);
        // Remove a trailing comma before the closing bracket if present
        $s = preg_replace('/,\s*\]$/m', ']', $s);
        // Replace the final ']' with '])'
        if (str_ends_with($s, ']')) {
            $s = substr($s, 0, -1) . "\n])";
        }
        return $s;
    }

    /**
     * Format a single cell.
     *
     * @param mixed $cell
     * @param int $precision
     * @return string
     */
    protected static function formatCell(mixed $cell, int $precision): string {
        $s = 'Unknown';
        if (is_int($cell) || is_float($cell)) {
            $s = self::formatNumber($cell, $precision);
        } elseif (is_string($cell)) {
            $s = "'" . addslashes($cell) . "'";
        } elseif (is_bool($cell)) {
            $s = $cell ? 'True' : 'False';
        } elseif (is_null($cell)) {
            $s = 'None';
        } elseif (is_array($cell)) {
            $s = 'Array';
        } elseif (is_object($cell)) {
            $s = 'Object';
        } elseif (is_resource($cell)) {
            $s = 'Resource';
        }
        return $s;
    }
}
