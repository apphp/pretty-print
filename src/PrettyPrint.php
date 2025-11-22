<?php

declare(strict_types=1);

namespace Apphp\PrettyPrint;

/**
 * PrettyPrint
 *
 * Callable pretty-printer for PHP arrays that mimics Python/PyTorch formatting.
 * Use as an object: (new PrettyPrint())(...$args) or via the global function pprint(...$args).
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
class PrettyPrint {
    // version: 0.2.1
    // Added format2DTorch

    // ---- Private helpers ----
    private function formatNumber($v): string {
        if (is_int($v)) return (string)$v;
        if (is_float($v)) return number_format($v, 4, '.', '');
        return (string)$v;
    }

    private function is1D($value): bool {
        if (!is_array($value)) return false;
        foreach ($value as $cell) {
            if (!is_int($cell) && !is_float($cell)) return false;
        }
        return true;
    }

    private function is2D($value): bool {
        if (!is_array($value)) return false;
        if (empty($value)) return true;
        foreach ($value as $row) {
            if (!is_array($row)) return false;
            foreach ($row as $cell) {
                if (!is_int($cell) && !is_float($cell)) return false;
            }
        }
        return true;
    }

    private function is3D($value): bool {
        if (!is_array($value)) return false;
        foreach ($value as $matrix) {
            if (!$this->is2D($matrix)) return false;
        }
        return true;
    }

    private function format2DAligned(array $matrix): string {
        $cols = 0;
        foreach ($matrix as $row) {
            if (is_array($row)) $cols = max($cols, count($row));
        }
        if ($cols === 0) return '[]';

        // Pre-format all numeric cells and compute widths in one pass
        $widths = array_fill(0, $cols, 0);
        $formatted = [];
        foreach ($matrix as $r => $row) {
            $frow = [];
            for ($c = 0; $c < $cols; $c++) {
                if (isset($row[$c]) && (is_int($row[$c]) || is_float($row[$c]))) {
                    $s = $this->formatNumber($row[$c]);
                } else if (isset($row[$c])) {
                    // Non-numeric encountered → fallback generic formatting
                    return '[' . implode(', ', array_map(fn($r2) => $this->formatForArray($r2), $matrix)) . ']';
                } else {
                    $s = '';
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

        if (count($lines) === 1) return '[' . $lines[0] . ']';
        return "[" . implode(",\n ", $lines) . "]";
    }

    private function format2DSummarized(array $matrix, int $headRows = 2, int $tailRows = 2, int $headCols = 3, int $tailCols = 3): string {
        $rows = count($matrix);
        $cols = 0;
        foreach ($matrix as $row) { if (is_array($row)) { $cols = max($cols, count($row)); } }

        $rowIdxs = [];
        $useRowEllipsis = false;
        if ($rows <= $headRows + $tailRows) {
            for ($r = 0; $r < $rows; $r++) $rowIdxs[] = $r;
        } else {
            for ($r = 0; $r < $headRows; $r++) $rowIdxs[] = $r;
            $useRowEllipsis = true;
            for ($r = $rows - $tailRows; $r < $rows; $r++) $rowIdxs[] = $r;
        }

        $colPositions = [];
        if ($cols <= $headCols + $tailCols) {
            for ($c = 0; $c < $cols; $c++) $colPositions[] = $c;
        } else {
            for ($c = 0; $c < $headCols; $c++) $colPositions[] = $c;
            $colPositions[] = '...';
            for ($c = $cols - $tailCols; $c < $cols; $c++) $colPositions[] = $c;
        }

        // Pre-format selected cells and compute widths in one pass
        $widths = array_fill(0, count($colPositions), 0);
        $formatted = [];
        foreach ($rowIdxs as $rIndex) {
            $frow = [];
            foreach ($colPositions as $i => $pos) {
                if ($pos === '...') {
                    $s = '...';
                } else if (isset($matrix[$rIndex][$pos]) && (is_int($matrix[$rIndex][$pos]) || is_float($matrix[$rIndex][$pos]))) {
                    $s = $this->formatNumber($matrix[$rIndex][$pos]);
                } else {
                    $s = '';
                }
                $frow[$i] = $s;
                $widths[$i] = max($widths[$i], strlen($s));
            }
            $formatted[] = $frow;
        }
        foreach ($colPositions as $i => $pos) { if ($pos === '...') $widths[$i] = max($widths[$i], 3); }

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
        for ($i = 0; $i < $headCount; $i++) $lines[] = $buildRow($formatted[$i]);
        if ($rows > $headRows + $tailRows) $lines[] = ' ...';
        if ($rows > $headRows + $tailRows) {
            $total = count($formatted);
            for ($i = $headCount; $i < $total; $i++) $lines[] = $buildRow($formatted[$i]);
        }

        if (count($lines) === 1) return '[' . $lines[0] . ']';
        return '[' . trim(implode(",\n ", $lines)) . ']';
    }

    private function formatForArray($value): string {
        if (is_array($value)) {
            if ($this->is2D($value)) return $this->format2DAligned($value);
            $formattedItems = array_map(fn($v) => $this->formatForArray($v), $value);
            return '[' . implode(', ', $formattedItems) . ']';
        }
        if (is_int($value) || is_float($value)) return $this->formatNumber($value);
        if (is_bool($value)) return $value ? 'True' : 'False';
        if (is_null($value)) return 'None';
        return "'" . addslashes((string)$value) . "'";
    }

    private function format3DTorch(array $tensor3d, int $headB = 3, int $tailB = 3, int $headRows = 2, int $tailRows = 3, int $headCols = 3, int $tailCols = 3): string {
        $B = count($tensor3d);
        $idxs = [];
        $useBEllipsis = false;
        if ($B <= $headB + $tailB) {
            for ($i = 0; $i < $B; $i++) $idxs[] = $i;
        } else {
            for ($i = 0; $i < $headB; $i++) $idxs[] = $i;
            $useBEllipsis = true;
            for ($i = $B - $tailB; $i < $B; $i++) $idxs[] = $i;
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
        if ($useBEllipsis) $blocks[] = ' ...';
        if ($useBEllipsis) {
            for ($i = $limitHead; $i < count($idxs); $i++) {
                $formatted2d = $format2d($tensor3d[$idxs[$i]]);
                $blocks[] = ' ' . str_replace("\n", "\n ", $formatted2d);
            }
        }

        $joined = implode(",\n\n ", $blocks);
        return "tensor([\n " . $joined . "\n])";
    }

    // 2D tensor pretty-print in PyTorch style using summarized 2D formatter
    private function format2DTorch(array $matrix, int $headRows = 2, int $tailRows = 3, int $headCols = 3, int $tailCols = 3): string {
        $s = $this->format2DSummarized($matrix, $headRows, $tailRows, $headCols, $tailCols);
        // Replace the very first '[' with 'tensor([['
        if (strlen($s) > 0 && $s[0] === '[') {
            $s = "tensor([\n  " . substr($s, 1);
        } else {
            return 'tensor(' . $s . ')';
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
    public function __invoke(...$args) {
        $end = PHP_EOL;
        if (isset($args['end'])) {
            $end = (string)$args['end'];
            unset($args['end']);
        } else if (!empty($args)) {
            $last = end($args);
            if (is_array($last) && array_key_exists('end', $last)) {
                $end = (string)($last['end'] ?? '');
                array_pop($args);
            }
            reset($args);
        }

        // Extract optional tensor formatting options from trailing options array
        $fmt = [];
        // 1) Support PHP named arguments for formatting keys
        $fmtKeys = ['headB','tailB','headRows','tailRows','headCols','tailCols'];
        foreach ($fmtKeys as $k) {
            if (array_key_exists($k, $args)) {
                $fmt[$k] = $args[$k];
                unset($args[$k]);
            }
        }
        if (!empty($args)) {
            $last = end($args);
            if (is_array($last)) {
                $hasFmt = false;
                foreach ($fmtKeys as $k) { if (array_key_exists($k, $last)) { $hasFmt = true; break; } }
                if ($hasFmt) {
                    // Merge trailing array options (takes precedence over named if both provided)
                    $fmt = array_merge($fmt, $last);
                    array_pop($args);
                    reset($args);
                }
            }
        }

        $args = array_values($args);

        // Label + single 3D tensor
        if (count($args) === 2 && !is_array($args[0]) && is_array($args[1]) && $this->is3D($args[1])) {
            $out = $this->format3DTorch(
                $args[1],
                (int)($fmt['headB'] ?? 3),
                (int)($fmt['tailB'] ?? 3),
                (int)($fmt['headRows'] ?? 2),
                (int)($fmt['tailRows'] ?? 3),
                (int)($fmt['headCols'] ?? 3),
                (int)($fmt['tailCols'] ?? 3)
            );
            echo $out . $end;
            return;
        }

        // Label + 2D matrix
        if (count($args) === 2 && !is_array($args[0]) && is_array($args[1]) && $this->is2D($args[1])) {
            $label = is_bool($args[0]) ? ($args[0] ? 'True' : 'False') : (is_null($args[0]) ? 'None' : (string)$args[0]);
            $out = $this->format2DAligned($args[1]);
            echo ($label . "\n" . $out) . $end;
            return;
        }

        // Multiple 1D rows → align
        $label = null; $rows = [];
        if (count($args) > 1) {
            $startIndex = 0;
            if (!is_array($args[0])) {
                $label = is_bool($args[0]) ? ($args[0] ? 'True' : 'False') : (is_null($args[0]) ? 'None' : (string)$args[0]);
                $startIndex = 1;
            }
            $allRows = true;
            for ($i = $startIndex; $i < count($args); $i++) {
                if ($this->is1D($args[$i])) { $rows[] = $args[$i]; } else { $allRows = false; break; }
            }
            if ($allRows && count($rows) > 1) {
                $out = $this->format2DAligned($rows);
                echo (($label !== null) ? ($label . "\n" . $out) : $out) . $end;
                return;
            }
        }

        // Default formatting
        $parts = [];
        foreach ($args as $arg) {
            if (is_array($arg)) {
                if ($this->is3D($arg)) {
                    $parts[] = $this->format3DTorch(
                        $arg,
                        (int)($fmt['headB'] ?? 3),
                        (int)($fmt['tailB'] ?? 3),
                        (int)($fmt['headRows'] ?? 2),
                        (int)($fmt['tailRows'] ?? 3),
                        (int)($fmt['headCols'] ?? 3),
                        (int)($fmt['tailCols'] ?? 3)
                    );
                } elseif ($this->is2D($arg)) {
                    $parts[] = $this->format2DTorch(
                        $arg,
                        (int)($fmt['headRows'] ?? 2),
                        (int)($fmt['tailRows'] ?? 3),
                        (int)($fmt['headCols'] ?? 3),
                        (int)($fmt['tailCols'] ?? 3)
                    );
                } else {
                    $parts[] = $this->formatForArray($arg);
                }
            } elseif (is_bool($arg)) {
                $parts[] = $arg ? 'True' : 'False';
            } elseif (is_null($arg)) {
                $parts[] = 'None';
            } elseif (is_int($arg) || is_float($arg)) {
                $parts[] = $this->formatNumber($arg);
            } else {
                $parts[] = (string)$arg;
            }
        }

        echo implode(' ', $parts) . $end;
    }
}


