<?php

namespace {

    use Apphp\PrettyPrint\PrettyPrint;

    /**
     * Convenience wrapper around PrettyPrint's callable interface.
     * @param ...$args
     * @return void
     */
    function pprint(...$args): void {
        (new PrettyPrint())(...$args);
    }

    /**
     * Alias for pprint
     * @param ...$args
     * @return void
     */
    function pp(...$args): void {
        pprint(...$args);
    }

    /**
     * Alias for pprint with exit after printing
     * @param ...$args
     * @return void
     */
    function ppd(...$args): void {
        pprint(...$args);
        exit;
    }
}
