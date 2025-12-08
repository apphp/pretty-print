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

