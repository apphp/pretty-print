<?php

namespace Apphp\PrettyPrint;

class Helper
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

    /**
     * Determine if a value is a 1D array of int|float|string|bool|null.
     *
     * @param mixed $value
     * @return bool True if $value is an array where every element is int or float.
     */
    public static function is1D(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $cell) {
            if (!is_scalar($cell) && $cell !== null) {
                return false;
            }
        }
        return true;
    }

    /**
     * Determine if the given value is a 2D numeric matrix.
     *
     * Accepts empty arrays as 2D.
     *
     * @param mixed $value
     * @return bool True if $value is an array of arrays of int|float.
     */
    public static function is2D(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }
        if (empty($value)) {
            return true;
        }
        foreach ($value as $row) {
            if (!is_array($row)) {
                return false;
            }
            foreach ($row as $cell) {
                if (!is_scalar($cell) && $cell !== null) {
                    return false;
                }
            }
        }
        return true;
    }
}
