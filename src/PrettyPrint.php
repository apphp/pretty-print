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
        [$start, $sep, $end, $args] = $this->parseSimpleOptions($args);
        [$fmt, $start, $sep, $end, $args] = $this->extractFormattingOptions($args, $start, $sep, $end);
        $fmt = $this->sanitizeFormattingOptions($fmt);

        $args = $this->normalizeArgs($args);

        $prevPrecision = $this->precision;
        if (isset($fmt['precision'])) {
            $this->precision = max(0, (int)$fmt['precision']);
        }

        if ($this->tryLabel3D($args, $fmt, $start, $end, $prevPrecision)) {
            return;
        }

        if ($this->tryLabel2D($args, $start, $end, $prevPrecision)) {
            return;
        }

        if ($this->tryMultiple1DRows($args, $start, $end, $prevPrecision)) {
            return;
        }

        $parts = $this->formatDefaultParts($args, $fmt);
        $this->applyAutoPreWrapping(Env::isCli(), $start, $end);
        echo $start . implode($sep, $parts) . $end;
        $this->precision = $prevPrecision;
    }

    /**
     * Parse simple named options (start, sep, end) from the variadic args.
     *
     * @param array $args
     * @return array [string $start, string $sep, string $end, array $args]
     */
    private function parseSimpleOptions(array $args): array
    {
        $end = PHP_EOL;
        $start = '';
        $sep = ' ';

        if (isset($args['end'])) {
            $end = (string)$args['end'];
            unset($args['end']);
        }
        if (isset($args['start'])) {
            $start = (string)$args['start'];
            unset($args['start']);
        }
        if (isset($args['sep'])) {
            $sep = (string)$args['sep'];
            unset($args['sep']);
        }

        return [$start, $sep, $end, $args];
    }

    /**
     * Extract formatting options from named args and optional trailing array.
     * Trailing array takes precedence over named args.
     *
     * @param array $args
     * @param string $start
     * @param string $sep
     * @param string $end
     * @return array [array $fmt, string $start, string $sep, string $end, array $args]
     */
    private function extractFormattingOptions(array $args, string $start, string $sep, string $end): array
    {
        $fmt = [];
        $fmtKeys = ['headB', 'tailB', 'headRows', 'tailRows', 'headCols', 'tailCols', 'label', 'precision'];
        foreach ($fmtKeys as $k) {
            if (array_key_exists($k, $args)) {
                $fmt[$k] = $args[$k];
                unset($args[$k]);
            }
        }

        if (!empty($args)) {
            $last = end($args);
            if (is_array($last)) {
                $hasOptions = false;
                $optionKeys = array_merge(['end', 'start', 'sep'], $fmtKeys);
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
                    if (array_key_exists('sep', $last)) {
                        $sep = (string)$last['sep'];
                    }
                    $fmt = array_merge($fmt, $last);
                    array_pop($args);
                    reset($args);
                }
            }
        }

        return [$fmt, $start, $sep, $end, $args];
    }

    /**
     * Clamp and sanitize formatting options (precision, head/tail, label length).
     *
     * @param array $fmt
     * @return array
     */
    private function sanitizeFormattingOptions(array $fmt): array
    {
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
        return $fmt;
    }

    /**
     * Auto-wrap output in <pre> tags when not running in CLI.
     *
     * @param bool $isCli
     * @param string $start
     * @param string $end
     * @return void
     */
    private function applyAutoPreWrapping(bool $isCli, string &$start, string &$end): void
    {
        if (!$isCli) {
            $start = '<pre>' . $start;
            $end = $end . '</pre>';
        }
    }

    /**
     * Remove unknown named args, reindex, and cap the number of args.
     *
     * @param array $args
     * @return array
     */
    private function normalizeArgs(array $args): array
    {
        foreach ($args as $k => $_v) {
            if (!is_int($k)) {
                unset($args[$k]);
            }
        }
        $args = array_values($args);
        if (count($args) > self::MAX_ARGS) {
            $args = array_slice($args, 0, self::MAX_ARGS);
        }
        return $args;
    }

    /**
     * Handle the special case: label + single 3D tensor.
     * Echoes output and restores precision if matched.
     *
     * @param array $args
     * @param array $fmt
     * @param string $start
     * @param string $end
     * @param int $prevPrecision
     * @return bool True if handled, false otherwise
     */
    private function tryLabel3D(array $args, array $fmt, string $start, string $end, int $prevPrecision): bool
    {
        if (count($args) === 2 && !is_array($args[0]) && is_array($args[1]) && Validator::is3D($args[1])) {
            $out = Formatter::format3DTorch(
                $args[1],
                (int)($fmt['headB'] ?? 5),
                (int)($fmt['tailB'] ?? 5),
                (int)($fmt['headRows'] ?? 5),
                (int)($fmt['tailRows'] ?? 5),
                (int)($fmt['headCols'] ?? 5),
                (int)($fmt['tailCols'] ?? 5),
                (string)($fmt['label'] ?? 'tensor'),
                $this->precision
            );
            echo $start . $out . $end;
            $this->precision = $prevPrecision;
            return true;
        }
        return false;
    }

    /**
     * Handle the special case: label + single 2D matrix.
     * Echoes output and restores precision if matched.
     *
     * @param array $args
     * @param string $start
     * @param string $end
     * @param int $prevPrecision
     * @return bool True if handled, false otherwise
     */
    private function tryLabel2D(array $args, string $start, string $end, int $prevPrecision): bool
    {
        if (count($args) === 2 && !is_array($args[0]) && is_array($args[1]) && Validator::is2D($args[1])) {
            $label = is_bool($args[0]) ? ($args[0] ? 'True' : 'False') : (is_null($args[0]) ? 'None' : (string)$args[0]);
            $out = Formatter::format2DAligned($args[1], $this->precision);
            echo $start . ($label . "\n" . $out) . $end;
            $this->precision = $prevPrecision;
            return true;
        }
        return false;
    }

    /**
     * Handle the case of multiple 1D rows (optionally preceded by a label) and align them.
     * Echoes output and restores precision if matched.
     *
     * @param array $args
     * @param string $start
     * @param string $end
     * @param int $prevPrecision
     * @return bool True if handled, false otherwise
     */
    private function tryMultiple1DRows(array $args, string $start, string $end, int $prevPrecision): bool
    {
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
                return true;
            }
        }
        return false;
    }

    /**
     * Default formatting for mixed args: scalars, arrays (1D, 2D, 3D).
     * Returns an array of pre-formatted string parts.
     *
     * @param array $args
     * @param array $fmt
     * @return array
     */
    private function formatDefaultParts(array $args, array $fmt): array
    {
        $parts = [];
        foreach ($args as $arg) {
            if (is_array($arg)) {
                if (Validator::is3D($arg)) {
                    $parts[] = Formatter::format3DTorch(
                        $arg,
                        (int)($fmt['headB'] ?? 5),
                        (int)($fmt['tailB'] ?? 5),
                        (int)($fmt['headRows'] ?? 5),
                        (int)($fmt['tailRows'] ?? 5),
                        (int)($fmt['headCols'] ?? 5),
                        (int)($fmt['tailCols'] ?? 5),
                        (string)($fmt['label'] ?? 'tensor'),
                        $this->precision
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
                    $parts[] = Formatter::formatForArray($arg, $this->precision);
                }
            } else {
                $parts[] = Formatter::formatCell($arg, $this->precision);
            }
        }
        return $parts;
    }
}
