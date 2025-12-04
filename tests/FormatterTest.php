<?php

declare(strict_types=1);

namespace Apphp\PrettyPrint\Tests;

use Apphp\PrettyPrint\Formatter;
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

    public static function format2DAlignedProvider(): array
    {
        return [
            'two numeric rows align' => [
                [[1, 23, 456], [12, 3, 45]],
                2,
                "[[ 1, 23, 456],\n [12,  3,  45]]",
            ],
            'ragged rows pad missing cells' => [
                [[1, 23], [12, 3, 45]],
                2,
                "[[ 1, 23,   ],\n [12,  3, 45]]",
            ],
            'strings quoted and escaped' => [
                [["a'b", 'c']],
                2,
                "[[a'b, c]]",
            ],
            'booleans and null rendered' => [
                [[true, false, null]],
                2,
                '[[True, False, None]]',
            ],
            'floats obey precision' => [
                [[1.2, 3.4567], [9.0, 10.9999]],
                2,
                "[[1.20,  3.46],\n [9.00, 11.00]]",
            ],
            'empty matrix' => [
                [],
                2,
                '[]',
            ],
            'array cell casts via (string)$cell' => [
                [[[1, 2]]],
                2,
                '[[Array]]',
            ],
            'object cell renders as Object' => [
                [[(object)['a' => 1]]],
                2,
                '[[Object]]',
            ],
            'resource cell renders as Resource' => [
                [[fopen('php://memory', 'r')]],
                2,
                '[[Resource]]',
            ],
            'closed resource renders as Unknown' => (function () {
                $h = fopen('php://temp', 'r+');
                fclose($h);
                return [
                    [[$h]],
                    2,
                    '[[Unknown]]',
                ];
            })(),
        ];
    }

    #[Test]
    #[TestDox('format2DAligned formats 2D arrays with alignment, quoting, and precision')]
    #[DataProvider('format2DAlignedProvider')]
    public function testFormat2DAligned(array $matrix, int $precision, string $expected): void
    {
        self::assertSame($expected, Formatter::format2DAligned($matrix, $precision));
    }

    public static function format2DSummarizedProvider(): array
    {
        return [
            'no truncation behaves like aligned (ints)' => [
                [[1, 23, 456], [12, 3, 45]],
                5, 5, 5, 5,
                2,
                "[[ 1, 23, 456],\n  [12,  3,  45]]",
            ],
            'row and column truncation with ellipses (floats with precision)' => [
                [[1, 2, 3], [4, 5, 6], [7, 8, 9.001]],
                1, 1, 1, 1,
                2,
                "[[1, ...,    3],\n  ...,\n [7, ..., 9.00]]",
            ],
            'single row with strings/booleans/null' => [
                [["a'b", true, null, false]],
                5, 5, 5, 5,
                2,
                "[[a'b, True, None, False]]",
            ],
        ];
    }

    #[Test]
    #[TestDox('format2DSummarized formats with head/tail rows/cols and quotes/precision')]
    #[DataProvider('format2DSummarizedProvider')]
    public function testFormat2DSummarized(array $matrix, int $headRows, int $tailRows, int $headCols, int $tailCols, int $precision, string $expected): void
    {
        self::assertSame($expected, Formatter::format2DSummarized($matrix, $headRows, $tailRows, $headCols, $tailCols, $precision));
    }

    public static function format2DTorchProvider(): array
    {
        return [
            'default label tensor with no truncation' => [
                [[1, 23, 456], [fopen('php://memory', 'r'), [], (object)[]]],
                5, 5, 5, 5,
                'tensor',
                2,
                "tensor([\n   [       1,    23,    456],\n   [Resource, Array, Object]\n])",
            ],
            'custom label and precision, with truncation' => [
                [[1, 2, 3], [4, 5, 6], [7, 8, 9.001]],
                1, 1, 1, 1,
                'mat',
                2,
                "mat([\n   [1, ...,    3],\n   ...,\n  [7, ..., 9.00]\n])",
            ],
        ];
    }

    #[Test]
    #[TestDox('format2DTorch wraps summarized 2D output with label([...]) and proper indentation')]
    #[DataProvider('format2DTorchProvider')]
    public function testFormat2DTorch(array $matrix, int $headRows, int $tailRows, int $headCols, int $tailCols, string $label, int $precision, string $expected): void
    {
        self::assertSame($expected, Formatter::format2DTorch($matrix, $headRows, $tailRows, $headCols, $tailCols, $label, $precision));
    }

    public static function format3DTorchProvider(): array
    {
        return [
            'two 2x2 blocks, no truncation' => [
                [[[1, 2], [3, 4]], [[5, 6], [7, 8]]],
                5, 5, 5, 5, 5, 5,
                'tensor',
                2,
                "tensor([\n  [[1, 2],\n   [3, 4]],\n\n  [[5, 6],\n   [7, 8]]\n])",
            ],
            'block ellipsis with inner 2D ellipses' => [
                // 3D: 3 blocks so headB=1, tailB=1 yields a middle ellipsis
                [
                    [[1, 2, 3], [4, 5, 6], [7, 8, 9]],
                    [[1, 2, 3], [4, 5, 6], [7, 8, 9]],
                    [[1, 2, 3], [4, 5, 6], [7, 8, 9]],
                ],
                1, 1, 1, 1, 1, 1,
                'tensor',
                2,
                "tensor([\n  [[1, ..., 3],\n   ...,\n  [7, ..., 9]],\n\n  ...,\n\n  [[1, ..., 3],\n   ...,\n  [7, ..., 9]]\n])",
            ],
        ];
    }

    #[Test]
    #[TestDox('format3DTorch wraps multiple 2D blocks with proper spacing, supports block ellipsis and inner 2D summarization')]
    #[DataProvider('format3DTorchProvider')]
    public function testFormat3DTorch(array $tensor3d, int $headB, int $tailB, int $headRows, int $tailRows, int $headCols, int $tailCols, string $label, int $precision, string $expected): void
    {
        self::assertSame($expected, Formatter::format3DTorch($tensor3d, $headB, $tailB, $headRows, $tailRows, $headCols, $tailCols, $label, $precision));
    }
}
