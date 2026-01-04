<?php

declare(strict_types=1);

namespace Apphp\PrettyPrint;

use ReflectionException;

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
    private array $labels = [];

    /**
     * Invoke the pretty printer.
     *
     * @param mixed ...$args
     * @return string
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
     * - 'start' => string                    // prefix printed before the content, default ""
     * - 'end' => string                      // line terminator, default "\n\n"
     * - 'sep' => string                      // separator between multiple default-formatted arguments, default "\n"
     * - 'label' => string                    // prefix label for 2D/3D formatted arrays, default `tensor`
     * - 'precision' => int                   // number of digits after the decimal point for floats, default 4
     * - 'return' => bool                     // when true, do not echo; return the formatted string instead
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
    public function __invoke(...$args): string
    {
        [$start, $sep, $end, $args] = $this->parseSimpleOptions($args);
        [$fmt, $start, $sep, $end, $args, $returnString] = $this->extractFormattingOptions($args, $start, $sep, $end);

        $fmt = $this->sanitizeFormattingOptions($fmt);
        $args = $this->normalizeArgs($args);

        if (!$returnString) {
            $this->applyAutoPreWrapping(Env::isCli(), $start, $end);
        }

        $prevPrecision = $this->precision;
        if (isset($fmt['precision'])) {
            $this->precision = max(0, (int) $fmt['precision']);
        }

        $out = $start . implode($sep, $this->formatDefaultParts($args, $fmt)) . $end;

        // Restore default precision
        $this->precision = $prevPrecision;

        if (!$returnString) {
            echo $out;
        }

        return $out;
    }

    /**
     * Parse simple named options (start, sep, end) from the variadic args.
     *
     * @param array $args
     * @return array [string $start, string $sep, string $end, array $args]
     */
    private function parseSimpleOptions(array $args): array
    {
        $nl = PHP_EOL;
        $end = $nl . $nl;
        $start = '';
        $sep = $nl;

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
        $returnString = false;
        $fmtKeys = ['headB', 'tailB', 'headRows', 'tailRows', 'headCols', 'tailCols', 'label', 'precision', 'rowsOnly', 'colsOnly'];
        foreach ($fmtKeys as $k) {
            if (array_key_exists($k, $args)) {
                $fmt[$k] = $args[$k];
                unset($args[$k]);
            }
        }
        if (array_key_exists('return', $args)) {
            $returnString = (bool)$args['return'];
            unset($args['return']);
        }

        if (!empty($args)) {
            $last = end($args);
            if (is_array($last)) {
                $hasOptions = false;
                $optionKeys = array_merge(['end', 'start', 'sep', 'return'], $fmtKeys);
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
                    if (array_key_exists('return', $last)) {
                        $returnString = (bool)$last['return'];
                    }
                    $fmt = array_merge($fmt, $last);
                    array_pop($args);
                    reset($args);
                }
            }
        }

        return [$fmt, $start, $sep, $end, $args, $returnString];
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
     * @throws ReflectionException
     */
    private function normalizeArgs(array $args): array
    {
        foreach ($args as $k => $_v) {
            if (!is_int($k)) {
                unset($args[$k]);
            }
        }
        $args = array_values($args);

        // Convert objects to arrays if possible
        foreach ($args as $i => $value) {
            if (!is_object($value)) {
                continue;
            }

            $array = null;

            if (is_callable([$value, 'asArray'])) {
                $array = $value->asArray();
            } elseif (is_callable([$value, 'toArray'])) {
                $array = $value->toArray();
            }

            if (!is_array($array)) {
                continue;
            }

            // If this is a 2D structure with associative rows, normalize rows to indexed arrays
            if (Validator::is2D($array)) {
                foreach ($array as $rowIndex => $row) {
                    if (is_array($row) && !array_is_list($row)) {
                        $array[$rowIndex] = array_values($row);
                    }
                }
            }

            $args[$i] = $array;
            $this->labels[$i] = (new \ReflectionClass($value))->getShortName();
        }

        if (count($args) > self::MAX_ARGS) {
            $args = array_slice($args, 0, self::MAX_ARGS);
        }
        return $args;
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
        foreach ($args as $i => $arg) {
            if (is_array($arg)) {
                if (Validator::is3D($arg)) {
                    $rowsRange = $this->parseRangeOption($fmt['rowsOnly'] ?? null);
                    $colsRange = $this->parseRangeOption($fmt['colsOnly'] ?? null);
                    if ($rowsRange !== null || $colsRange !== null) {
                        $arg = $this->applyRowColFilters3D($arg, $rowsRange, $colsRange);
                    }
                    $parts[] = Formatter::format3DTorch(
                        $arg,
                        (int)($fmt['headB'] ?? 5),
                        (int)($fmt['tailB'] ?? 5),
                        (int)($fmt['headRows'] ?? 5),
                        (int)($fmt['tailRows'] ?? 5),
                        (int)($fmt['headCols'] ?? 5),
                        (int)($fmt['tailCols'] ?? 5),
                        (string)($fmt['label'] ?? ($this->labels[$i] ?? 'tensor')),
                        $this->precision
                    );
                } elseif (Validator::is2D($arg)) {
                    $rowsRange = $this->parseRangeOption($fmt['rowsOnly'] ?? null);
                    $colsRange = $this->parseRangeOption($fmt['colsOnly'] ?? null);
                    if ($rowsRange !== null || $colsRange !== null) {
                        $arg = $this->applyRowColFilters2D($arg, $rowsRange, $colsRange);
                    }
                    $parts[] = Formatter::format2DTorch(
                        $arg,
                        (int)($fmt['headRows'] ?? 5),
                        (int)($fmt['tailRows'] ?? 5),
                        (int)($fmt['headCols'] ?? 5),
                        (int)($fmt['tailCols'] ?? 5),
                        (string)($fmt['label'] ?? ($this->labels[$i] ?? 'tensor')),
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

    /**
     * Parse a row/column selector option.
     *
     * Accepts:
     * - single int (1-based)
     * - numeric string, e.g. "3"
     * - range string "A-B"
     * - comma-separated mix of indices and ranges, e.g. "1-2,5,9-11".
     *
     * Returns a sorted list of unique 1-based indices, or null when invalid/empty.
     *
     * @param mixed $value
     * @return array<int>|null
     */
    private function parseRangeOption(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            if ($value < 1) {
                return null;
            }
            return [$value];
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }

            if (ctype_digit($value)) {
                $idx = (int)$value;
                if ($idx < 1) {
                    return null;
                }
                return [$idx];
            }

            $indices = [];

            $segments = explode(',', $value);
            foreach ($segments as $segment) {
                $segment = trim($segment);
                if ($segment === '') {
                    continue;
                }

                if (ctype_digit($segment)) {
                    $n = (int)$segment;
                    if ($n >= 1) {
                        $indices[] = $n;
                    }
                    continue;
                }

                $parts = explode('-', $segment, 2);
                if (count($parts) === 2) {
                    $start = trim($parts[0]);
                    $end = trim($parts[1]);
                    if ($start !== '' && $end !== '' && ctype_digit($start) && ctype_digit($end)) {
                        $s = (int)$start;
                        $e = (int)$end;
                        if ($s >= 1 && $e >= $s) {
                            for ($k = $s; $k <= $e; $k++) {
                                $indices[] = $k;
                            }
                        }
                    }
                }
            }

            if (!empty($indices)) {
                $indices = array_values(array_unique($indices));
                sort($indices);
                return $indices;
            }
        }

        return null;
    }

    /**
     * Apply sparse row/column filters to a 2D matrix.
     *
     * @param array $matrix 2D array to slice.
     * @param array<int>|null $rowsRange List of 1-based row indices to keep, or null for all.
     * @param array<int>|null $colsRange List of 1-based column indices to keep, or null for all.
     * @return array Filtered 2D matrix.
     */
    private function applyRowColFilters2D(array $matrix, ?array $rowsRange, ?array $colsRange): array
    {
        $rows = count($matrix);
        if ($rowsRange !== null) {
            $selectedRows = [];
            foreach ($rowsRange as $idx) {
                if ($idx < 1 || $idx > $rows) {
                    continue;
                }
                $selectedRows[] = $matrix[$idx - 1];
            }
            $matrix = $selectedRows;
            $rows = count($matrix);
        }

        if ($colsRange !== null && !empty($matrix)) {
            foreach ($matrix as $ri => $row) {
                $values = array_values($row);
                $cols = count($values);
                $selected = [];
                foreach ($colsRange as $cIdx) {
                    if ($cIdx < 1 || $cIdx > $cols) {
                        continue;
                    }
                    $selected[] = $values[$cIdx - 1];
                }
                $matrix[$ri] = $selected;
            }
        }

        return $matrix;
    }

    /**
     * Apply sparse row/column filters to each 2D slice of a 3D tensor.
     *
     * @param array $tensor3d 3D tensor (array of 2D matrices).
     * @param array<int>|null $rowsRange List of 1-based row indices to keep, or null for all.
     * @param array<int>|null $colsRange List of 1-based column indices to keep, or null for all.
     * @return array Filtered 3D tensor.
     */
    private function applyRowColFilters3D(array $tensor3d, ?array $rowsRange, ?array $colsRange): array
    {
        foreach ($tensor3d as $bi => $matrix) {
            if (is_array($matrix)) {
                $tensor3d[$bi] = $this->applyRowColFilters2D($matrix, $rowsRange, $colsRange);
            }
        }
        return $tensor3d;
    }
}
