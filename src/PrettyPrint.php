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
        if (count($args) === 2 && !is_array($args[0]) && is_array($args[1]) && Validator::is3D($args[1])) {
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
        if (count($args) === 2 && !is_array($args[0]) && is_array($args[1]) && Validator::is2D($args[1])) {
            $label = is_bool($args[0]) ? ($args[0] ? 'True' : 'False') : (is_null($args[0]) ? 'None' : (string)$args[0]);
            $out = Formatter::format2DAligned($args[1], $this->precision);
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
                if (Validator::is1D($args[$i])) {
                    $rows[] = $args[$i];
                } else {
                    $allRows = false;
                    break;
                }
            }
            if ($allRows && count($rows) > 1) {
                $out = Formatter::format2DAligned($rows, $this->precision);
                echo $start . ((($label !== null) ? ($label . "\n" . $out) : $out)) . $end;
                $this->precision = $prevPrecision;
                return;
            }
        }

        // Default formatting
        $parts = [];

        foreach ($args as $arg) {
            if (is_array($arg)) {
                if (Validator::is3D($arg)) {
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
                } elseif (Validator::is2D($arg)) {
                    $parts[] = Formatter::format2DTorch(
                        $arg,
                        (int)($fmt['headRows'] ?? 5),
                        (int)($fmt['tailRows'] ?? 5),
                        (int)($fmt['headCols'] ?? 5),
                        (int)($fmt['tailCols'] ?? 5),
                        (string)($fmt['label'] ?? 'tensor'),
                        $this->precision
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
                    $parts[] = Formatter::formatNumber($arg, $this->precision);
                } elseif (is_string($arg)) {
                    // Top-level strings: print as-is (no quotes)
                    $parts[] = $arg;
                } elseif (is_array($arg)) {
                    $parts[] = 'Array';
                } elseif (is_object($arg)) {
                    $parts[] = 'Class';
                } elseif (is_resource($arg)) {
                    $parts[] = 'Resource';
                } else {
                    $parts[] = 'Unknown';
                }
            }
        }

        echo $start . implode(' ', $parts) . $end;
        $this->precision = $prevPrecision;
    }

    // ---- Private helpers ----

    /**
     * Generic array-aware formatter producing Python-like representations.
     *
     * @param mixed $value Scalar or array value to format.
     * @return string
     */
    private function formatForArray($value): string
    {
        if (is_array($value)) {
            if (Validator::is2D($value)) {
                return Formatter::format2DAligned($value, $this->precision);
            }
            $formattedItems = array_map(fn ($v) => $this->formatForArray($v), $value);
            return '[' . implode(', ', $formattedItems) . ']';
        }
        if (is_int($value) || is_float($value)) {
            return Formatter::formatNumber($value, $this->precision);
        }
        if (is_bool($value)) {
            return $value ? 'True' : 'False';
        }
        if (is_null($value)) {
            return 'None';
        }
        if (is_string($value)) {
            return "'" . addslashes($value) . "'";
        }
        if (is_object($value)) {
            return 'Object';
        }
        if (is_resource($value)) {
            return 'Resource';
        }
        return 'Unknown';
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
            return Formatter::format2DSummarized($matrix, $headRows, $tailRows, $headCols, $tailCols, $this->precision);
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

}

// 672/605/499/394==
