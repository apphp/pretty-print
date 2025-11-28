<?php

declare(strict_types=1);

namespace Apphp\PrettyPrint;

/**
 * PrettyPrint
 *
 * Callable pretty-printer for PHP arrays that mimics Python/PyTorch formatting.
 * Use as an object: (new PrettyPrint())(...$args) or via the global function pprint(...$args).
 *
 * @author Samuel Akopyan <leumas.a@gmail.com>
 *
 * Examples:
 * - Basic printing (strings, numbers):
 *   (new PrettyPrint())('Hello', 123, 4.56);
 *
 * - 1D rows aligned as a matrix:
 *   $pp = new PrettyPrint();
 *   $pp([1, 23, 456], [12, 3, 45]);
 *
 * - Label + aligned 2D matrix:
 *   $pp('Confusion matrix:', [[1, 23], [456, 7]]);
 *
 * - 2D tensor style (PyTorch-like) with options:
 *   $pp($matrix2d, ['headRows' => 3, 'tailRows' => 2, 'headCols' => 4, 'tailCols' => 4]);
 *
 * - 3D tensor style with block summarization:
 *   $pp($tensor3d, ['headB' => 3, 'tailB' => 3, 'headRows' => 2, 'tailRows' => 3, 'headCols' => 3, 'tailCols' => 3]);
 *
 * - Custom line terminator (like Python's end=):
 *   $pp('Line without newline', ['end' => '']);
 */
class PrettyPrint
{
    private const MAX_PRECISION = 10;
    private const MAX_HEAD_TAIL = 50;
    private const MAX_LABEL_LEN = 50;
    private const MAX_ARGS = 32;
    private int $precision = 4;

    // ---- Callable main entry ----
    /**
     * Invoke the pretty printer.
     *
     * Supported arguments patterns:
     * - Scalars/strings: will be printed space-separated.
     * - Multiple 1D arrays: treated as rows and aligned in columns.
     * - Label + 2D array: prints label on first line and aligned matrix below.
     * - Label + 3D array: prints label on first line and tensor([...]) below.
     * - Single 2D array: prints tensor([[...]], ...) like PyTorch.
     * - Single 3D array: prints tensor([...]) with head/tail summarization.
     *
     * Options (pass as trailing array):
     * - 'end' => string            // line terminator, default "\n"
     * - 'headB' => int, 'tailB' => int       // number of head/tail 2D blocks for 3D tensors
     * - 'headRows' => int, 'tailRows' => int // rows per 2D slice to show (with ellipsis if truncated)
     * - 'headCols' => int, 'tailCols' => int // columns per 2D slice to show (with ellipsis if truncated)
     *
     * Call examples:
     *   (new PrettyPrint())('Metrics:', ['end' => "\n\n"]);
     *   (new PrettyPrint())([1,2,3], [4,5,6]);
     *   (new PrettyPrint())($matrix2d, ['headRows' => 4, 'tailRows' => 0]);
     *   (new PrettyPrint())($tensor3d, ['headB' => 4, 'tailB' => 2]);
     */
    public function __invoke(...$args)
    {
        $end = PHP_EOL;
        $start = '';

        // Named args for simple options
        if (isset($args['end'])) {
            $end = (string)$args['end'];
            unset($args['end']);
        }
        if (isset($args['start'])) {
            $start = (string)$args['start'];
            unset($args['start']);
        }

        // Extract optional tensor formatting options from trailing options array
        $fmt = [];
        // 1) Support PHP named arguments for formatting keys
        $fmtKeys = ['headB', 'tailB', 'headRows', 'tailRows', 'headCols', 'tailCols', 'label', 'precision'];
        foreach ($fmtKeys as $k) {
            if (array_key_exists($k, $args)) {
                $fmt[$k] = $args[$k];
                unset($args[$k]);
            }
        }
        // 2) Trailing array options: may contain start/end and/or formatting keys
        if (!empty($args)) {
            $last = end($args);
            if (is_array($last)) {
                $hasOptions = false;
                $optionKeys = array_merge(['end', 'start'], $fmtKeys);
                foreach ($optionKeys as $k) {
                    if (array_key_exists($k, $last)) {
                        $hasOptions = true;
                        break;
                    }
                }
                if ($hasOptions) {
                    if (array_key_exists('end', $last)) {
                        $end = (string)$last['end'];
                    }
                    if (array_key_exists('start', $last)) {
                        $start = (string)$last['start'];
                    }
                    // Merge trailing array options (takes precedence over named if both provided)
                    $fmt = array_merge($fmt, $last);
                    array_pop($args);
                    reset($args);
                }
            }
        }

        // Sanitize formatting options: clamp ranges and label length
        if (!empty($fmt)) {
            if (isset($fmt['precision'])) {
                $fmt['precision'] = max(0, min(self::MAX_PRECISION, (int)$fmt['precision']));
            }
            foreach (['headB','tailB','headRows','tailRows','headCols','tailCols'] as $key) {
                if (isset($fmt[$key])) {
                    $fmt[$key] = max(0, min(self::MAX_HEAD_TAIL, (int)$fmt[$key]));
                }
            }
            if (isset($fmt['label'])) {
                $fmt['label'] = (string)$fmt['label'];
                if (strlen($fmt['label']) > self::MAX_LABEL_LEN) {
                    $fmt['label'] = substr($fmt['label'], 0, self::MAX_LABEL_LEN);
                }
            }
        }

        // Auto-wrap with <pre> for web (non-CLI) usage
        $isCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
        if (!$isCli) {
            $start = '<pre>' . $start;
            $end = $end . '</pre>';
        }

        // Remove any unknown named arguments so they don't get printed as stray scalars
        foreach ($args as $k => $_v) {
            if (!is_int($k)) {
                unset($args[$k]);
            }
        }
        $args = array_values($args);
        if (count($args) > self::MAX_ARGS) {
            $args = array_slice($args, 0, self::MAX_ARGS);
        }

        // Apply numeric precision if provided; remember previous to restore later
        $prevPrecision = $this->precision;
        if (isset($fmt['precision'])) {
            $this->precision = max(0, (int)$fmt['precision']);
        }

        // Label + single 3D tensor
        if (count($args) === 2 && !is_array($args[0]) && is_array($args[1]) && $this->is3D($args[1])) {
            $out = $this->format3DTorch(
                $args[1],
                (int)($fmt['headB'] ?? 5),
                (int)($fmt['tailB'] ?? 5),
                (int)($fmt['headRows'] ?? 5),
                (int)($fmt['tailRows'] ?? 5),
                (int)($fmt['headCols'] ?? 5),
                (int)($fmt['tailCols'] ?? 5),
                (string)($fmt['label'] ?? 'tensor')
            );
            echo $start . $out . $end;
            $this->precision = $prevPrecision;
            return;
        }

        // Label + 2D matrix (supports numeric and string matrices)
        if (count($args) === 2 && !is_array($args[0]) && is_array($args[1]) && Helper::is2D($args[1])) {
            $label = is_bool($args[0]) ? ($args[0] ? 'True' : 'False') : (is_null($args[0]) ? 'None' : (string)$args[0]);
            $out = $this->format2DAligned($args[1]);
            echo $start . ($label . "\n" . $out) . $end;
            $this->precision = $prevPrecision;
            return;
        }

        // Multiple 1D rows → align
        $label = null;
        $rows = [];
        if (count($args) > 1) {
            $startIndex = 0;
            if (!is_array($args[0])) {
                $label = is_bool($args[0]) ? ($args[0] ? 'True' : 'False') : (is_null($args[0]) ? 'None' : (string)$args[0]);
                $startIndex = 1;
            }
            $allRows = true;
            for ($i = $startIndex; $i < count($args); $i++) {
                if (Helper::is1D($args[$i])) {
                    $rows[] = $args[$i];
                } else {
                    $allRows = false;
                    break;
                }
            }
            if ($allRows && count($rows) > 1) {
                $out = $this->format2DAligned($rows);
                echo $start . ((($label !== null) ? ($label . "\n" . $out) : $out)) . $end;
                $this->precision = $prevPrecision;
                return;
            }
        }

        foreach ($args as $arg) {
            if (is_array($arg)) {
                if ($this->is3D($arg)) {
                    $parts[] = $this->format3DTorch(
                        $arg,
                        (int)($fmt['headB'] ?? 5),
                        (int)($fmt['tailB'] ?? 5),
                        (int)($fmt['headRows'] ?? 5),
                        (int)($fmt['tailRows'] ?? 5),
                        (int)($fmt['headCols'] ?? 5),
                        (int)($fmt['tailCols'] ?? 5),
                        (string)($fmt['label'] ?? 'tensor')
                    );
                } elseif (Helper::is2D($arg)) {
                    $parts[] = $this->format2DTorch(
                        $arg,
                        (int)($fmt['headRows'] ?? 5),
                        (int)($fmt['tailRows'] ?? 5),
                        (int)($fmt['headCols'] ?? 5),
                        (int)($fmt['tailCols'] ?? 5),
                        (string)($fmt['label'] ?? 'tensor')
                    );
                } else {
                    $parts[] = $this->formatForArray($arg);
                }
            } else {
                if (is_bool($arg)) {
                    $parts[] = $arg ? 'True' : 'False';
                } elseif (is_null($arg)) {
                    $parts[] = 'None';
                } elseif (is_int($arg) || is_float($arg)) {
                    $parts[] = Helper::formatNumber($arg, $this->precision);
                } else {
                    $parts[] = (string)$arg;
                }
            }
        }

        echo $start . implode(' ', $parts) . $end;
        $this->precision = $prevPrecision;
    }

    // ---- Private helpers ----

    // TODO: >>>>>>>>
    /**
     * Determine if the given value is a 3D tensor of numeric matrices.
     *
     * @param mixed $value
     * @return bool True if $value is an array of 2D numeric arrays.
     */
    private function is3D($value): bool
    {
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $matrix) {
            if (!$this->is2DNumeric($matrix)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Determine if the given value is a 2D numeric matrix (ints/floats only).
     *
     * @param mixed $value
     * @return bool
     */
    private function is2DNumeric($value): bool
    {
        if (!is_array($value)) {
            return false;
        }
        if (empty($value)) {
            return true;
        }
        foreach ($value as $row) {
            if (!is_array($row)) {
                return false;
            }
            foreach ($row as $cell) {
                if (!is_int($cell) && !is_float($cell)) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Format a 2D numeric matrix with aligned columns.
     *
     * @param array $matrix 2D array of ints/floats.
     * @return string
     */
    private function format2DAligned(array $matrix): string
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
                        $s = Helper::formatNumber($cell, $this->precision);
                    } elseif (is_string($cell)) {
                        $s = "'" . addslashes($cell) . "'";
                    } elseif (is_bool($cell)) {
                        $s = $cell ? 'True' : 'False';
                    } elseif (is_null($cell)) {
                        $s = 'None';
                    } else {
                        $s = (string)$cell;
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

    /**
     * Format a 2D matrix showing head/tail rows and columns with ellipses in-between.
     *
     * @param array $matrix 2D array of ints/floats.
     * @param int $headRows Number of head rows to display.
     * @param int $tailRows Number of tail rows to display.
     * @param int $headCols Number of head columns to display.
     * @param int $tailCols Number of tail columns to display.
     * @return string
     */
    private function format2DSummarized(array $matrix, int $headRows = 5, int $tailRows = 5, int $headCols = 5, int $tailCols = 5): string
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
                } elseif (isset($matrix[$rIndex][$pos])) {
                    $cell = $matrix[$rIndex][$pos];
                    if (is_int($cell) || is_float($cell)) {
                        $s = Helper::formatNumber($cell, $this->precision);
                    } elseif (is_string($cell)) {
                        $s = "'" . addslashes($cell) . "'";
                    } elseif (is_bool($cell)) {
                        $s = $cell ? 'True' : 'False';
                    } elseif (is_null($cell)) {
                        $s = 'None';
                    } else {
                        $s = (string)$cell;
                    }
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
     * Generic array-aware formatter producing Python-like representations.
     *
     * @param mixed $value Scalar or array value to format.
     * @return string
     */
    private function formatForArray($value): string
    {
        if (is_array($value)) {
            if (Helper::is2D($value)) {
                return $this->format2DAligned($value);
            }
            $formattedItems = array_map(fn ($v) => $this->formatForArray($v), $value);
            return '[' . implode(', ', $formattedItems) . ']';
        }
        if (is_int($value) || is_float($value)) {
            return Helper::formatNumber($value, $this->precision);
        }
        if (is_bool($value)) {
            return $value ? 'True' : 'False';
        }
        if (is_null($value)) {
            return 'None';
        }
        return "'" . addslashes((string)$value) . "'";
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
     * @param string $label Prefix label used instead of "tensor".
     * @return string
     */
    private function format3DTorch(array $tensor3d, int $headB = 5, int $tailB = 5, int $headRows = 5, int $tailRows = 5, int $headCols = 5, int $tailCols = 5, string $label = 'tensor'): string
    {
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
        $format2d = function ($matrix) use ($headRows, $tailRows, $headCols, $tailCols) {
            return $this->format2DSummarized($matrix, $headRows, $tailRows, $headCols, $tailCols);
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

        $joined = implode(",\n\n ", $blocks);
        return $label . "([\n " . $joined . "\n])";
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
     * @return string
     */
    private function format2DTorch(array $matrix, int $headRows = 5, int $tailRows = 5, int $headCols = 5, int $tailCols = 5, string $label = 'tensor'): string
    {
        $s = $this->format2DSummarized($matrix, $headRows, $tailRows, $headCols, $tailCols);
        // Replace the very first '[' with 'tensor([['
        if (strlen($s) > 0 && $s[0] === '[') {
            $s = $label . "([\n  " . substr($s, 1);
        } else {
            return $label . '(' . $s . ')';
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
}

// 672/605==
