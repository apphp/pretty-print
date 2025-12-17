<?php

declare(strict_types=1);

namespace Apphp\PrettyPrint;

/**
 * Convenience wrapper around PrettyPrint's callable interface.
 * @param ...$args
 * @return string
 */
function pprint(...$args): string
{
    return (new PrettyPrint())(...$args);
}

/**
 * Alias for pprint
 * @param ...$args
 * @return string
 */
function pp(...$args): string
{
    return pprint(...$args);
}

/**
 * Alias for pprint with exit after printing
 * @param ...$args
 */
function ppd(...$args)
{
    $exiter = null;

    if (Env::isCli() && getenv('APP_ENV') === 'test') {
        $exiter = fn () => null;
    }

    $exiter ??= fn () => exit;

    // Execute behavior
    pprint(...$args);
    $exiter();
}

/**
 * Print a difference matrix between two arrays.
 *
 * For each corresponding element, prints the same value when equal,
 * or '-' when different or missing.
 *
 * @param array $a
 * @param array $b
 * @return string
 */
function pdiff(array $a, array $b): string
{
    $rows = max(count($a), count($b));
    $diff = [];

    for ($i = 0; $i < $rows; $i++) {
        $rowA = $a[$i] ?? [];
        $rowB = $b[$i] ?? [];

        // Handle scalar-vs-scalar rows directly
        if (!is_array($rowA) && !is_array($rowB)) {
            $diff[] = [$rowA === $rowB ? $rowA : '-'];
            continue;
        }

        // Normalize to row arrays
        $rowA = is_array($rowA) ? $rowA : [$rowA];
        $rowB = is_array($rowB) ? $rowB : [$rowB];

        $cols = max(count($rowA), count($rowB));
        $rowDiff = [];
        for ($j = 0; $j < $cols; $j++) {
            $vA = $rowA[$j] ?? null;
            $vB = $rowB[$j] ?? null;
            $rowDiff[] = ($vA === $vB) ? $vA : '-';
        }
        $diff[] = $rowDiff;
    }

    return pprint($diff);
}

/**
 * Print a comparison matrix between two arrays.
 *
 * For each corresponding element, prints the same value when equal,
 * or '-' when different or missing.
 *
 * @param array $a
 * @param array $b
 * @param array $options
 * @return string
 */
function pcompare(array $a, array $b, array $options = []): string
{
    $nl = PHP_EOL;
    $start = '';
    $end = $nl . $nl;
    $precision = 4;
    $returnString = false;

    if (isset($options['start'])) {
        $start = (string) $options['start'];
    }
    if (isset($options['end'])) {
        $end = (string) $options['end'];
    }
    if (isset($options['precision'])) {
        $precision = max(0, (int) $options['precision']);
    }
    if (isset($options['return'])) {
        $returnString = (bool) $options['return'];
    }

    $isCli = Env::isCli();

    $rows = max(count($a), count($b));
    $cols = 0;

    for ($i = 0; $i < $rows; $i++) {
        $rowA = $a[$i] ?? [];
        $rowB = $b[$i] ?? [];

        $rowA = is_array($rowA) ? $rowA : [$rowA];
        $rowB = is_array($rowB) ? $rowB : [$rowB];

        $cols = max($cols, count($rowA), count($rowB));
    }

    if ($cols === 0) {
        $out = $start;
        if (!$isCli) {
            $out .= '<pre>';
        }
        $out .= '[]' . $nl . $nl . '[]' . $end;
        if (!$isCli) {
            $out .= '</pre>';
        }
        if (!$returnString) {
            echo $out;
        }
        return $out;
    }

    $cellTextA = [];
    $cellTextB = [];
    $cellRawA = [];
    $cellRawB = [];
    $existsA = [];
    $existsB = [];
    $widths = array_fill(0, $cols, 0);

    for ($i = 0; $i < $rows; $i++) {
        $rowA = $a[$i] ?? [];
        $rowB = $b[$i] ?? [];

        $rowA = is_array($rowA) ? $rowA : [$rowA];
        $rowB = is_array($rowB) ? $rowB : [$rowB];

        for ($j = 0; $j < $cols; $j++) {
            $hasA = array_key_exists($j, $rowA);
            $hasB = array_key_exists($j, $rowB);

            $existsA[$i][$j] = $hasA;
            $existsB[$i][$j] = $hasB;

            $cellRawA[$i][$j] = $hasA ? $rowA[$j] : null;
            $cellRawB[$i][$j] = $hasB ? $rowB[$j] : null;

            $sA = $hasA ? Formatter::formatCell($rowA[$j], $precision) : '-';
            $sB = $hasB ? Formatter::formatCell($rowB[$j], $precision) : '-';

            $cellTextA[$i][$j] = $sA;
            $cellTextB[$i][$j] = $sB;

            $widths[$j] = max($widths[$j], strlen($sA), strlen($sB));
        }
    }

    $wrapCell = function (string $text, bool $same) use ($isCli): string {
        if ($isCli) {
            $color = $same ? "\033[32m" : "\033[31m";
            return $color . $text . "\033[0m";
        }

        $safe = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $color = $same ? 'green' : 'red';
        return '<span style="color: ' . $color . '">' . $safe . '</span>';
    };

    $buildMatrixLines = function (array $cells, array $rawSelf, array $rawOther, array $existsSelf, array $existsOther) use ($rows, $cols, $widths, $wrapCell): array {
        $lines = [];
        for ($i = 0; $i < $rows; $i++) {
            $parts = [];
            for ($j = 0; $j < $cols; $j++) {
                $same = ($existsSelf[$i][$j] ?? false) && ($existsOther[$i][$j] ?? false) && (($rawSelf[$i][$j] ?? null) === ($rawOther[$i][$j] ?? null));
                $text = str_pad($cells[$i][$j] ?? '-', $widths[$j], ' ', STR_PAD_LEFT);
                $parts[] = $wrapCell($text, $same);
            }
            $lines[] = '[' . implode(', ', $parts) . ']';
        }
        return $lines;
    };

    $linesA = $buildMatrixLines($cellTextA, $cellRawA, $cellRawB, $existsA, $existsB);
    $linesB = $buildMatrixLines($cellTextB, $cellRawB, $cellRawA, $existsB, $existsA);

    $out = $start;
    if (!$isCli) {
        $out .= '<pre>';
    }

    $out .= implode($nl, $linesA) . $nl . $nl . implode($nl, $linesB) . $end;

    if (!$isCli) {
        $out .= '</pre>';
    }

    if (!$returnString) {
        echo $out;
    }

    return $out;
}
