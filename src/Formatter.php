<?php

namespace Apphp\PrettyPrint;

/**
 * Formatter for pretty-printing arrays.
 */
class Formatter
{
    /**
     * Normalize a matrix row so non-list arrays are treated as positional values.
     *
     * @param mixed $row
     * @return array
     */
    private static function normalizeRow(mixed $row): array
    {
        if (!is_array($row)) {
            return [];
        }

        return array_is_list($row) ? $row : array_values($row);
    }

    /**
     * Format a value as a number when possible.
     *
     * Integers are returned verbatim; floats are rendered with 4 decimal places;
     * non-numeric values are cast to string.
     *
     * @param mixed $v The value to format.
     * @param int $precision Number of decimal places to use for floats.
     * @param bool $short Whether to trim trailing zeros in float output.
     * @return string
     */
    public static function formatNumber(mixed $v, int $precision = 4, bool $short = false): string
    {
        if (is_int($v)) {
            return (string)$v;
        }
        if (is_float($v)) {
            $formatted = number_format($v, $precision, '.', '');
            if ($short) {
                $formatted = rtrim(rtrim($formatted, '0'), '.');
                if ($formatted === '-0' || $formatted === '0') {
                    $formatted = '0';
                }
            }
            return $formatted;
        }
        return (string)$v;
    }

    /**
     * Format a 2D matrix with aligned columns.
     *
     * @param array $matrix 2D array.
     * @param int $precision Number of decimal places to use for floats.
     * @param bool $short Whether to trim trailing zeros in float output.
     * @return string
     */
    public static function format2DAligned(array $matrix, int $precision = 4, bool $short = false): string
    {
        $cols = 0;
        foreach ($matrix as $row) {
            $values = self::normalizeRow($row);
            $cols = max($cols, count($values));
        }
        if ($cols === 0) {
            return '[]';
        }

        // Pre-format all cells (numbers and strings) and compute widths in one pass
        $widths = array_fill(0, $cols, 0);
        $formatted = [];
        foreach ($matrix as $r => $row) {
            $values = self::normalizeRow($row);
            $frow = [];
            for ($c = 0; $c < $cols; $c++) {
                $s = '';
                if (array_key_exists($c, $values)) {
                    $cell = $values[$c];
                    $s = self::formatCell($cell, $precision, true, $short);
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
     * @param bool $short Whether to trim trailing zeros in float output.
     * @param bool $colsSummary Whether to show summary of columns.
     * @param string $colsSummaryLabel Label shown above the per-column sums row.
     * @param bool $rowsSummary Whether to show summary of rows.
     * @param string $rowsSummaryLabel Label shown in the first row for the row-sums column.
     * @return string
     */
    public static function format2DSummarized(
        array $matrix,
        int $headRows = 5,
        int $tailRows = 5,
        int $headCols = 5,
        int $tailCols = 5,
        int $precision = 4,
        bool $short = false,
        bool $colsSummary = false,
        string $colsSummaryLabel = '---totals---',
        bool $rowsSummary = false,
        string $rowsSummaryLabel = '---totals---'
    ): string {
        $rows = count($matrix);
        $cols = 0;
        foreach ($matrix as $row) {
            $values = self::normalizeRow($row);
            $cols = max($cols, count($values));
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
            $values = self::normalizeRow($matrix[$rIndex] ?? []);
            $frow = [];
            foreach ($colPositions as $i => $pos) {
                $s = '';
                if ($pos === '...') {
                    $s = '...';
                } elseif (array_key_exists($pos, $values)) {
                    $cell = $values[$pos];
                    $s = self::formatCell($cell, $precision, true, $short);
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

        $summaryRow = null;
        if ($colsSummary) {
            $summaryRow = [];
            foreach ($colPositions as $i => $pos) {
                if ($pos === '...') {
                    $s = '...';
                    $summaryRow[$i] = $s;
                    $widths[$i] = max($widths[$i], 3);
                    continue;
                }

                $sum = 0;
                $hasNumeric = false;
                foreach ($matrix as $row) {
                    $values = self::normalizeRow($row);
                    if (!array_key_exists($pos, $values)) {
                        continue;
                    }

                    $cell = $values[$pos];
                    if (is_int($cell) || is_float($cell)) {
                        $sum += $cell;
                        $hasNumeric = true;
                    }
                }

                $s = $hasNumeric ? self::formatNumber($sum, $precision, $short) : '';
                $summaryRow[$i] = $s;
                $widths[$i] = max($widths[$i], strlen($s));
            }
        }

        $rowsSummaryHeaderRow = null;

        if ($rowsSummary) {
            $summaryColIndex = count($widths);
            $widths[] = 0;

            foreach ($rowIdxs as $i => $rowIndex) {
                $values = self::normalizeRow($matrix[$rowIndex] ?? []);
                $sum = 0;
                $hasNumeric = false;
                foreach ($values as $cell) {
                    if (is_int($cell) || is_float($cell)) {
                        $sum += $cell;
                        $hasNumeric = true;
                    }
                }

                $summaryValue = $hasNumeric ? self::formatNumber($sum, $precision, $short) : '';
                $formatted[$i][$summaryColIndex] = $summaryValue;
                $widths[$summaryColIndex] = max($widths[$summaryColIndex], strlen($summaryValue));
            }

            if (count($formatted) > 0) {
                $rowsSummaryHeaderRow = array_fill(0, count($widths), '');
                $rowsSummaryHeaderRow[$summaryColIndex] = $rowsSummaryLabel;
                $widths[$summaryColIndex] = max($widths[$summaryColIndex], strlen($rowsSummaryLabel));
            }
        }

        // Build lines from pre-formatted rows
        $buildRow = function (array $frow, int $headCount) use ($widths) {
            $cells = [];
            foreach ($frow as $i => $s) {
                $cells[] = str_pad($s, $widths[$i], ' ', STR_PAD_LEFT);
            }
            // Add extra space to align columns like earlier tweak
            return ($headCount === 1 ? '' : ' ') . '[' . implode(', ', $cells) . ']';
        };

        $lines = [];
        $headCount = ($rows <= $headRows + $tailRows) ? count($rowIdxs) : $headRows;
        if ($rowsSummaryHeaderRow !== null) {
            $lines[] = $buildRow($rowsSummaryHeaderRow, $headCount);
        }
        for ($i = 0; $i < $headCount; $i++) {
            $lines[] = $buildRow($formatted[$i], $headCount);
        }
        if ($rows > $headRows + $tailRows) {
            $lines[] = ' ...';
        }
        if ($rows > $headRows + $tailRows) {
            $total = count($formatted);
            for ($i = $headCount; $i < $total; $i++) {
                $lines[] = $buildRow($formatted[$i], $headCount);
            }
        }
        if ($summaryRow !== null) {
            $lines[] = ' ' . $colsSummaryLabel;
            $lines[] = $buildRow($summaryRow, $headCount);
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
     * @param string $label Prefix label used instead of "array".
     * @param int $precision Number of decimal places to use for floats.
     * @param bool $short Whether to trim trailing zeros in float output.
     * @param bool $colsSummary Whether to show summary of columns.
     * @param string $colsSummaryLabel
     * @param bool $rowsSummary Whether to show summary of rows.
     * @param string $rowsSummaryLabel Label shown in the first row for the row-sums column.
     * @return string
     */
    public static function format2DTorch(
        array $matrix,
        int $headRows = 5,
        int $tailRows = 5,
        int $headCols = 5,
        int $tailCols = 5,
        string $label = 'array',
        int $precision = 4,
        bool $short = false,
        bool $colsSummary = false,
        string $colsSummaryLabel = '---totals---',
        bool $rowsSummary = false,
        string $rowsSummaryLabel = '---totals---'
    ): string {
        $s = self::format2DSummarized(
            $matrix,
            $headRows,
            $tailRows,
            $headCols,
            $tailCols,
            $precision,
            $short,
            $colsSummary,
            $colsSummaryLabel,
            $rowsSummary,
            $rowsSummaryLabel
        );
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
     * Generic array-aware formatter producing Python-like representations.
     *
     * @param mixed $value Scalar or array value to format.
     * @param int $precision Number of decimal places to use for floats.
     * @param bool $short Whether to trim trailing zeros in float output.
     * @return string
     */
    public static function formatForArray($value, int $precision = 4, bool $short = false): string
    {
        if (is_array($value)) {
            if (Validator::is2D($value)) {
                return self::format2DAligned($value, $precision, $short);
            }
            $formattedItems = array_map(fn ($v) => self::formatForArray($v, 4, $short), $value);
            return '[' . implode(', ', $formattedItems) . ']';
        }

        return self::formatCell($value, $precision, true, $short);
    }

    /**
     * Format a single cell.
     *
     * @param mixed $cell
     * @param int $precision Number of decimal places to use for floats.
     * @param bool $quoteStrings
     * @param bool $short Whether to trim trailing zeros in float output.
     * @return string
     */
    public static function formatCell(mixed $cell, int $precision, bool $quoteStrings = false, bool $short = false): string
    {
        $s = 'Unknown';
        if (is_int($cell) || is_float($cell)) {
            $s = self::formatNumber($cell, $precision, $short);
        } elseif (is_string($cell)) {
            $escaped = addslashes($cell);
            $s = $quoteStrings ? "'{$escaped}'" : (Env::isCli() ? $cell : $escaped);
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

    /**
     * Format a 3D numeric tensor in a PyTorch-like multiline representation.
     *
     * @param array $tensor3d 3D array of ints/floats.
     * @param int $headB Number of head 2D slices to display.
     * @param int $tailB Number of tail 2D slices to display.
     * @param int $headRows Number of head rows per 2D slice.
     * @param int $tailRows Number of tail rows per 2D slice.
     * @param int $headCols Number of head columns per 2D slice.
     * @param int $tailCols Number of tail columns per 2D slice.
     * @param string $label Prefix label used instead of "array".
     * @param int $precision Number of decimal places to use for floats.
     * @param bool $short Whether to trim trailing zeros in float output.
     * @param bool $colsSummary Whether to show summary of columns.
     * @param string $colsSummaryLabel Label shown above the per-column sums row (default: ---totals---)
     * @param bool $rowsSummary Whether to show summary of rows.
     * @param string $rowsSummaryLabel Label shown in the first row for the row-sums column (default: ---totals---)
     * @return string
     */
    public static function format3DTorch(
        array $tensor3d,
        int $headB = 5,
        int $tailB = 5,
        int $headRows = 5,
        int $tailRows = 5,
        int $headCols = 5,
        int $tailCols = 5,
        string $label = 'array',
        int $precision = 4,
        bool $short = false,
        bool $colsSummary = false,
        string $colsSummaryLabel = '---totals---',
        bool $rowsSummary = false,
        string $rowsSummaryLabel = '---totals---'
    ): string {
        $B = count($tensor3d);
        $idxs = [];
        $useBEllipsis = false;
        if ($B <= $headB + $tailB) {
            for ($i = 0; $i < $B; $i++) {
                $idxs[] = $i;
            }
        } else {
            for ($i = 0; $i < $headB; $i++) {
                $idxs[] = $i;
            }
            $useBEllipsis = true;
            for ($i = $B - $tailB; $i < $B; $i++) {
                $idxs[] = $i;
            }
        }

        $blocks = [];
        $format2d = function ($matrix) use ($headRows, $tailRows, $headCols, $tailCols, $precision, $short, $colsSummary, $colsSummaryLabel, $rowsSummary, $rowsSummaryLabel) {
            return self::format2DSummarized(
                $matrix,
                $headRows,
                $tailRows,
                $headCols,
                $tailCols,
                $precision,
                $short,
                $colsSummary,
                $colsSummaryLabel,
                $rowsSummary,
                $rowsSummaryLabel
            );
        };

        $limitHead = ($B <= $headB + $tailB) ? count($idxs) : $headB;
        for ($i = 0; $i < $limitHead; $i++) {
            $formatted2d = $format2d($tensor3d[$idxs[$i]]);
            // Indent entire block by a single space efficiently
            $blocks[] = ' ' . str_replace("\n", "\n ", $formatted2d);
        }
        if ($useBEllipsis) {
            $blocks[] = ' ...';
        }
        if ($useBEllipsis) {
            for ($i = $limitHead; $i < count($idxs); $i++) {
                $formatted2d = $format2d($tensor3d[$idxs[$i]]);
                $blocks[] = ' ' . str_replace("\n", "\n ", $formatted2d);
            }
        }

        // Use a single newline between blocks for simple tensors without batch
        $separator = ",\n ";
        $joined = implode($separator, $blocks);
        return $label . "([\n " . $joined . "\n])";
    }
}
