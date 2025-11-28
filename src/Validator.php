<?php

namespace Apphp\PrettyPrint;

class Validator
{
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

    /**
     * Determine if the given value is a 3D tensor of matrices.
     *
     * @param mixed $value
     * @return bool True if $value is an array of 2D arrays.
     */
    public static function is3D(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $matrix) {
            if (!self::is2D($matrix)) {
                return false;
            }
        }
        return true;
    }
}
