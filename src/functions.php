<?php

declare(strict_types=1);

namespace Apphp\PrettyPrint;

use Apphp\PrettyPrint\Env;
use Apphp\PrettyPrint\PrettyPrint;

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
        $exiter = fn() => null;
    }

    $exiter ??= fn() => exit;

    // Execute behavior
    pprint(...$args);
    $exiter();
}

/**
 * Print a difference matrix between two arrays.
 *
 * For each corresponding element, prints the same value when equal,
 * or 'x' when different or missing.
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

