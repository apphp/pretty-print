<?php

declare(strict_types=1);

use Apphp\PrettyPrint\PrettyPrint;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(PrettyPrint::class)]
#[Group('prettyprint')]
final class PrettyPrintTest extends TestCase
{
    #[Test]
    #[TestDox('prints scalars and strings with number formatting and newline')]
    public function scalarsAndStrings(): void
    {
        $pp = new PrettyPrint();
        ob_start();
        $pp('Hello', 123, 4.56);
        $out = ob_get_clean();
        self::assertSame("Hello 123 4.5600\n", $out);
    }

    #[Test]
    #[TestDox('aligns multiple 1D arrays as a 2D matrix')]
    public function alignedRows1D(): void
    {
        $pp = new PrettyPrint();
        ob_start();
        $pp([1, 23, 456], [12, 3, 45]);
        $out = ob_get_clean();
        $expected = "[[ 1, 23, 456],\n [12,  3,  45]]\n";
        self::assertSame($expected, $out);
    }

    #[Test]
    #[TestDox('prints label followed by aligned 2D matrix')]
    public function labelPlus2DMatrix(): void
    {
        $pp = new PrettyPrint();
        $matrix = [[1, 23], [456, 7]];
        ob_start();
        $pp('Confusion matrix:', $matrix);
        $out = ob_get_clean();
        $expected = "Confusion matrix:\n[[  1, 23],\n [456,  7]]\n";
        self::assertSame($expected, $out);
    }

    #[Test]
    #[TestDox('formats a small 2D array as tensor([[..], ..])')]
    public function tensor2DFormattingSmall(): void
    {
        $pp = new PrettyPrint();
        $matrix = [[1, 2], [3, 4]];
        ob_start();
        $pp($matrix);
        $out = ob_get_clean();

        // Relaxed check: structure and values present, accounting for padding/indentation
        self::assertTrue(str_starts_with($out, "tensor(["));
        self::assertTrue(str_ends_with($out, "])\n"));
        self::assertMatchesRegularExpression('/tensor\(\[.*\[\s*1,\s*2\s*\],\s*\n\s*\[\s*3,\s*4\s*\].*\]\)/s', rtrim($out));
    }

    #[Test]
    #[TestDox('formats a small 3D tensor with two 2D blocks and blank line separation')]
    public function tensor3DFormattingSmall(): void
    {
        $pp = new PrettyPrint();
        $tensor = [
            [[1, 2], [3, 4]],
            [[5, 6], [7, 8]],
        ];
        ob_start();
        $pp($tensor);
        $out = ob_get_clean();

        // Basic structure checks
        self::assertTrue(str_starts_with($out, "tensor(["));
        self::assertTrue(str_ends_with($out, "])\n"));

        // Should contain two 2D blocks formatted; allow padding spaces
        self::assertMatchesRegularExpression('/\[\s*1,\s*2\s*\]/', $out);
        self::assertMatchesRegularExpression('/\[\s*7,\s*8\s*\]/', $out);
        // Blocks are separated by a blank line in between (",\n\n ")
        self::assertStringContainsString("\n\n ", $out);
    }
}
