<?php

declare(strict_types=1);

namespace {

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
     * @return string
     */
    function ppd(...$args): string
    {
        return pprint(...$args);

        if (PHP_SAPI === 'cli' && getenv('APP_ENV') === 'test') {
            return '';
        }

        exit;
    }
}
