<?php

namespace Apphp\PrettyPrint;

/**
 * Environment utilities.
 */
class Env
{
    // Used for testing purposes only
    private static ?bool $cliOverride = null;

    /**
     * Check if the application is running in CLI mode.
     * @return bool
     */
    public static function isCli(): bool
    {
        if (self::$cliOverride !== null) {
            return self::$cliOverride;
        }
        return (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
    }

    public static function setCliOverride(?bool $value): void
    {
        self::$cliOverride = $value;
    }
}
