<?php

declare(strict_types=1);

namespace Apphp\PrettyPrint\Tests;

use Apphp\PrettyPrint\Formatter;
use Apphp\PrettyPrint\PrettyPrint;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Formatter::class)]
#[Group('PrettyPrint')]
final class FormatterTest extends TestCase
{
    public static function formatNumberProvider(): array
    {
        return [
            'int value ignored precision'     => [5, 2, '5'],
            'zero int'                        => [0, 4, '0'],
            'positive float with rounding'    => [1.23456, 2, '1.23'],
            'positive float with padding'     => [1.2, 4, '1.2000'],
            'negative float rounding'         => [-3.14159, 3, '-3.142'],
            'string passthrough'              => ['abc', 2, 'abc'],
            'null'                            => [null, 2, ''],
            'false casts to empty string'     => [false, 2, ''],
            'true casts to 1'                 => [true, 2, '1'],
            'zero precision rounds halves up' => [2.5, 0, '3'],
            'high precision pads zeros'       => [1.23, 6, '1.230000'],
            'small scientific notation'       => [1e-6, 8, '0.00000100'],
            'large integer unchanged'         => [123456789, 5, '123456789'],
        ];
    }

    public static function defaultFormatNumberProvider(): array
    {
        return [
            'int default precision ignored'   => [5, '5'],
            'zero int default'                => [0, '0'],
            'float rounds to 2 by default'    => [1.23456, '1.23'],
            'float pads to 2 by default'      => [1.2, '1.20'],
            'negative float default rounding' => [-3.14159, '-3.14'],
            'string passthrough default'      => ['abc', 'abc'],
            'null to empty string default'    => [null, ''],
            'true to 1 default'               => [true, '1'],
            'false to empty string default'   => [false, ''],
            'zero float default'              => [0.0, '0.00'],
        ];
    }

    #[Test]
    #[TestDox('formatNumber formats ints, floats (with precision), and falls back to string casting')]
    #[DataProvider('formatNumberProvider')]
    public function testFormatNumber(mixed $value, int $precision, string $expected): void
    {
        self::assertSame($expected, Formatter::formatNumber($value, $precision));
    }

    #[Test]
    #[TestDox('formatNumber uses default precision when none is provided')]
    #[DataProvider('defaultFormatNumberProvider')]
    public function testFormatNumberDefaultPrecision(mixed $value, string $expected): void
    {
        self::assertSame($expected, Formatter::formatNumber($value));
    }
}
