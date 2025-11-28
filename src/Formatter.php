<?php

namespace Apphp\PrettyPrint;

class Formatter
{
    /**
     * Format a value as a number when possible.
     *
     * Integers are returned verbatim; floats are rendered with 4 decimal places;
     * non-numeric values are cast to string.
     *
     * @param mixed $v
     * @param int $precision
     * @return string
     */
    public static function formatNumber(mixed $v, int $precision = 2): string
    {
        if (is_int($v)) {
            return (string)$v;
        }
        if (is_float($v)) {
            return number_format($v, $precision, '.', '');
        }
        return (string)$v;
    }
}
