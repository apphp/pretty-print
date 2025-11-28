<?php

declare(strict_types=1);

namespace Apphp\PrettyPrint\Tests;

use Apphp\PrettyPrint\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Validator::class)]
#[Group('PrettyPrint')]
final class ValidatorTest extends TestCase
{
    public static function is1DProvider(): array
    {
        return [
            'ints only'        => [[1, 2, 3], true],
            'floats only'      => [[1.0, 2.5, -3.14], true],
            'ints and floats'  => [[1, 2.0, -3.5], true],
            'empty array'      => [[], true],
            'contains string'  => [[1, '2', 3], true],
            'contains bool'    => [[1, true, 3], true],
            'contains null'    => [[1, null, 3], true],
            'nested array'     => [[1, [2], 3], false],
            'non-array int'    => [123, false],
            'non-array string' => ['abc', false],
        ];
    }

    #[Test]
    #[TestDox('is1D returns true only for 1D arrays of int|float|string|bool|null')]
    #[DataProvider('is1DProvider')]
    public function testIs1D(mixed $value, bool $expected): void
    {
        self::assertSame($expected, Validator::is1D($value));
    }

    public static function is2DProvider(): array
    {
        return [
            'empty outer array'                   => [[], true],
            'single empty row'                    => [[[ ]], true],
            'multiple empty rows'                 => [[[ ], [ ]], true],
            'numeric matrix'                      => [[[1,2,3],[4,5,6]], true],
            'mixed scalars'                       => [[[1,'2',true,null],[3.5, 'x', false, 0]], true],
            'ragged rows allowed'                 => [[[1,2],[3,4,5]], true],
            'assoc rows allowed'                  => [[['a' => 1,'b' => 2], ['c' => 3]], true],
            'row contains array (nested) invalid' => [[[1,[2],3]], false],
            'row is not array invalid'            => [[1,2,3], false],
            'outer non-array invalid'             => [123, false],
        ];
    }

    #[Test]
    #[TestDox('is2D returns true only for arrays-of-arrays with scalar|null cells')]
    #[DataProvider('is2DProvider')]
    public function testIs2D(mixed $value, bool $expected): void
    {
        self::assertSame($expected, Validator::is2D($value));
    }

    public static function is3DProvider(): array
    {
        return [
            'empty outer array'                           => [[], true],
            'single empty 2D matrix'                      => [[[]], true],
            'multiple empty 2D matrices'                  => [[[], []], true],
            'single 2D numeric matrix'                    => [[[[1,2,3],[4,5,6]]], true],
            'two 2D matrices ragged rows'                 => [[[[1,2],[3]], [[4,5,6],[7]]], true],
            '2D matrices with mixed scalars'              => [[[[1,'2',true,null],[3.5,'x',false,0]]], true],
            'outer contains non-array (invalid)'          => [[1,2,3], false],
            'matrix has row that is not array (invalid)'  => [[ [1,2,3] ], false],
            'matrix contains nested array cell (invalid)' => [[ [[1,[2],3]] ], false],
            'not an array invalid'                        => ['abc', false],
        ];
    }

    #[Test]
    #[TestDox('is3D returns true only for arrays of 2D arrays with scalar|null cells')]
    #[DataProvider('is3DProvider')]
    public function testIs3D(mixed $value, bool $expected): void
    {
        self::assertSame($expected, Validator::is3D($value));
    }
}
