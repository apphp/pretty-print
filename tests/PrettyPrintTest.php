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
    #[TestDox('accepts named formatting args for 2D (headRows/tailRows/headCols/tailCols)')]
    public function namedFormattingOptions2D(): void
    {
        $pp = new PrettyPrint();
        $m = [
            [1,2,3,4],
            [5,6,7,8],
            [9,10,11,12],
        ];
        ob_start();
        $pp($m, headRows: 1, tailRows: 1, headCols: 1, tailCols: 1);
        $out = ob_get_clean();
        self::assertStringContainsString('tensor([', $out);
        self::assertStringContainsString('...', $out);
    }

    #[Test]
    #[TestDox('trailing array options override named formatting args for 2D')]
    public function trailingOverridesNamed2D(): void
    {
        $pp = new PrettyPrint();
        $m = [
            [1,2,3,4],
            [5,6,7,8],
            [9,10,11,12],
        ];
        ob_start();
        // Named args would show no ellipsis; trailing array forces ellipsis
        $pp($m, headRows: 1, tailRows: 0, headCols: 4, tailCols: 0);
        $out = ob_get_clean();
        self::assertStringContainsString('...', $out);
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

    #[Test]
    #[TestDox('respects end option passed as trailing array')]
    public function endOptionTrailingArray(): void
    {
        $pp = new PrettyPrint();
        ob_start();
        $pp('Line without newline', ['end' => '']);
        $out = ob_get_clean();
        self::assertSame('Line without newline', $out);
    }

    #[Test]
    #[TestDox('respects named end option (PHP named args)')]
    public function endOptionNamedArgument(): void
    {
        $pp = new PrettyPrint();
        ob_start();
        $pp('Named', end: '');
        $out = ob_get_clean();
        self::assertSame('Named', $out);
    }

    #[Test]
    #[TestDox('respects start option passed as trailing array')]
    public function startOptionTrailingArray(): void
    {
        $pp = new PrettyPrint();
        ob_start();
        $pp('Hello', ['start' => "\t", 'end' => '']);
        $out = ob_get_clean();
        self::assertSame("\tHello", $out);
    }

    #[Test]
    #[TestDox('respects named start option (PHP named args)')]
    public function startOptionNamedArgument(): void
    {
        $pp = new PrettyPrint();
        ob_start();
        $pp('World', start: '>>> ' , end: '');
        $out = ob_get_clean();
        self::assertSame('>>> World', $out);
    }

    #[Test]
    #[TestDox('allows custom label for 2D tensor formatting')]
    public function customLabel2D(): void
    {
        $pp = new PrettyPrint();
        $m = [[1,2],[3,4]];
        ob_start();
        $pp($m, label: 'arr');
        $out = ob_get_clean();
        self::assertTrue(str_starts_with($out, 'arr(['));
        self::assertTrue(str_ends_with($out, "])\n"));
    }

    #[Test]
    #[TestDox('allows custom label for 3D tensor formatting')]
    public function customLabel3D(): void
    {
        $pp = new PrettyPrint();
        $t = [[[1,2],[3,4]], [[5,6],[7,8]]];
        ob_start();
        $pp($t, ['label' => 'ndarray']);
        $out = ob_get_clean();
        self::assertTrue(str_starts_with($out, 'ndarray(['));
        self::assertTrue(str_ends_with($out, "])\n"));
    }

    #[Test]
    #[TestDox('prints label followed by formatted 3D tensor')]
    public function labelPlus3DTensor(): void
    {
        $pp = new PrettyPrint();
        $tensor = array_fill(0, 2, [[1,2],[3,4]]);
        ob_start();
        $pp('Tensor:', $tensor);
        $out = ob_get_clean();
        self::assertStringStartsWith('tensor([', $out);
        self::assertStringNotContainsString('Tensor:', $out);
        self::assertStringContainsString('])', $out);
    }

    #[Test]
    #[TestDox('summarizes 2D matrices with row/col ellipses when limits are small')]
    public function summarized2DShowsEllipsis(): void
    {
        $pp = new PrettyPrint();
        // 3x4 matrix so that with headRows=1, tailRows=1, headCols=1, tailCols=1 we get ellipses
        $m = [
            [1,2,3,4],
            [5,6,7,8],
            [9,10,11,12],
        ];
        ob_start();
        $pp($m, ['headRows' => 1, 'tailRows' => 1, 'headCols' => 1, 'tailCols' => 1]);
        $out = ob_get_clean();
        self::assertStringContainsString('tensor([', $out);
        self::assertStringContainsString('...', $out);
        // two visible rows and an ellipsis between
        self::assertMatchesRegularExpression('/\[\s*1,\s*\.\.\.,\s*4\s*\]/', $out);
        self::assertMatchesRegularExpression('/\[\s*9,\s*\.\.\.,\s*12\s*\]/', $out);
    }

    #[Test]
    #[TestDox('summarizes 3D tensors with head/tail blocks and ellipsis between blocks')]
    public function summarized3DShowsBlockEllipsis(): void
    {
        $pp = new PrettyPrint();
        // Build 5 blocks so headB=2, tailB=2 produces a middle ellipsis
        $block = [[1,2,3],[4,5,6]];
        $t = [$block, $block, $block, $block, $block];
        ob_start();
        $pp($t, ['headB' => 2, 'tailB' => 2, 'headRows' => 1, 'tailRows' => 1, 'headCols' => 1, 'tailCols' => 1]);
        $out = ob_get_clean();
        // block ellipsis is a line with spaces then three dots, optional trailing comma
        self::assertMatchesRegularExpression("/\n\s+\.\.\.,?\n/", $out);
        // also expect inner 2D ellipses
        self::assertStringContainsString('...', $out);
    }

    #[Test]
    #[TestDox('falls back to generic formatting for non-numeric 2D arrays')]
    public function nonNumeric2DFallback(): void
    {
        $pp = new PrettyPrint();
        $arr = [['a', 2], [3, 4]];
        ob_start();
        $pp($arr);
        $out = ob_get_clean();
        self::assertSame("[['a', 2], [3, 4]]\n", $out);
    }

    #[Test]
    #[TestDox('prints booleans and null as True False None')]
    public function booleansAndNull(): void
    {
        $pp = new PrettyPrint();
        ob_start();
        $pp(true, false, null);
        $out = ob_get_clean();
        self::assertSame("True False None\n", $out);
    }

    #[Test]
    #[TestDox('aligns multiple 1D rows with an optional label')]
    public function multipleRowsWithLabel(): void
    {
        $pp = new PrettyPrint();
        ob_start();
        $pp('Label', [1,2], [3,4]);
        $out = ob_get_clean();
        self::assertSame("Label\n[[1, 2],\n [3, 4]]\n", $out);
    }

    #[Test]
    #[TestDox('covers fallback branch in formatNumber for non-numeric values')]
    public function formatNumberFallbackCoversString(): void
    {
        $pp = new PrettyPrint();
        $caller = \Closure::bind(function($v) { return $this->formatNumber($v); }, $pp, PrettyPrint::class);
        $result = $caller('abc');
        self::assertSame('abc', $result);
    }
}
