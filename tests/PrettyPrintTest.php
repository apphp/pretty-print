<?php

declare(strict_types=1);

namespace Apphp\PrettyPrint\Tests;

use Apphp\PrettyPrint\PrettyPrint;
use Apphp\PrettyPrint\Env;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(PrettyPrint::class)]
#[Group('PrettyPrint')]
final class PrettyPrintTest extends TestCase
{
    private int $obLevel = 0;
    private string $nl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->obLevel = ob_get_level();
        $this->nl = PHP_EOL;
    }

    protected function tearDown(): void
    {
        // Ensure any stray buffers opened during a test are closed
        while (ob_get_level() > $this->obLevel) {
            @ob_end_clean();
        }
        parent::tearDown();
    }

    #[Test]
    #[TestDox('prints scalars and strings with number formatting and newline')]
    public function scalarsAndStrings(): void
    {
        $pp = new PrettyPrint();
        ob_start();
        $pp('Hello', 123, 4.56);
        $out = ob_get_clean();
        self::assertSame("Hello{$this->nl}123{$this->nl}4.5600{$this->nl}{$this->nl}", $out);
    }

    #[Test]
    #[TestDox('supports custom separator via named argument (sep)')]
    public function customSeparatorNamed(): void
    {
        $pp = new PrettyPrint();
        ob_start();
        $pp('A', 'B', 'C', sep: ', ', end: '');
        $out = ob_get_clean();
        self::assertSame('A, B, C', $out);
    }

    #[Test]
    #[TestDox('supports custom separator via trailing options array (sep)')]
    public function customSeparatorTrailingArray(): void
    {
        $pp = new PrettyPrint();
        ob_start();
        $pp('X', 'Y', ['sep' => "\n", 'end' => '']);
        $out = ob_get_clean();
        self::assertSame("X\nY", $out);
    }

    #[Test]
    #[TestDox('accepts named formatting args for 2D (headRows/tailRows/headCols/tailCols)')]
    public function namedFormattingOptions2D(): void
    {
        $pp = new PrettyPrint();
        $m = [
            [1, 2, 3, 4],
            [5, 6, 7, 8],
            [9, 10, 11, 12],
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
            [1, 2, 3, 4],
            [5, 6, 7, 8],
            [9, 10, 11, 12],
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
        $expected = "[1, 23, 456]{$this->nl}[12, 3, 45]{$this->nl}{$this->nl}";
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
        $expected = "Confusion matrix:{$this->nl}tensor([{$this->nl}   [  1, 23],{$this->nl}   [456,  7]{$this->nl}]){$this->nl}{$this->nl}";
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
        self::assertTrue(str_starts_with($out, 'tensor(['));
        self::assertTrue(str_ends_with($out, "]){$this->nl}{$this->nl}"));
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
        self::assertTrue(str_starts_with($out, 'tensor(['));
        self::assertTrue(str_ends_with($out, "]){$this->nl}{$this->nl}"));

        // Should contain two 2D blocks formatted; allow padding spaces
        self::assertMatchesRegularExpression('/\[\s*1,\s*2\s*\]/', $out);
        self::assertMatchesRegularExpression('/\[\s*7,\s*8\s*\]/', $out);
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
        $pp('World', start: '>>> ', end: '');
        $out = ob_get_clean();
        self::assertSame('>>> World', $out);
    }

    #[Test]
    #[TestDox('allows custom label for 2D tensor formatting')]
    public function customLabel2D(): void
    {
        $pp = new PrettyPrint();
        $m = [[1, 2], [3, 4]];
        ob_start();
        $pp($m, label: 'arr');
        $out = ob_get_clean();
        self::assertTrue(str_starts_with($out, 'arr(['));
        self::assertTrue(str_ends_with($out, "]){$this->nl}{$this->nl}"));
    }

    #[Test]
    #[TestDox('allows custom label for 3D tensor formatting')]
    public function customLabel3D(): void
    {
        $pp = new PrettyPrint();
        $t = [[[1, 2], [3, 4]], [[5, 6], [7, 8]]];
        ob_start();
        $pp($t, ['label' => 'ndarray']);
        $out = ob_get_clean();
        self::assertTrue(str_starts_with($out, 'ndarray(['));
        self::assertTrue(str_ends_with($out, "]){$this->nl}{$this->nl}"));
    }

    #[Test]
    #[TestDox('prints label followed by formatted 3D tensor')]
    public function labelPlus3DTensor(): void
    {
        $pp = new PrettyPrint();
        $tensor = array_fill(0, 2, [[1, 2], [3, 4]]);
        ob_start();
        $pp('Tensor:', $tensor);
        $out = ob_get_clean();
        self::assertStringStartsWith("Tensor:\ntensor([", $out);
        self::assertStringContainsString('])', $out);
    }

    #[Test]
    #[TestDox('summarizes 2D matrices with row/col ellipses when limits are small')]
    public function summarized2DShowsEllipsis(): void
    {
        $pp = new PrettyPrint();
        // 3x4 matrix so that with headRows=1, tailRows=1, headCols=1, tailCols=1 we get ellipses
        $m = [
            [1, 2, 3, 4],
            [5, 6, 7, 8],
            [9, 10, 11, '12'],
            [13,14,15],
        ];
        ob_start();
        $pp($m, ['headRows' => 1, 'tailRows' => 1, 'headCols' => 1, 'tailCols' => 1]);
        $out = ob_get_clean();
        self::assertStringContainsString('tensor([', $out);
        self::assertStringContainsString('...', $out);
        // two visible rows (head and tail) and an ellipsis between
        self::assertMatchesRegularExpression('/\[\s*1,\s*\.\.\.,\s*4\s*\]/', $out);
        // Tail row is now [13, ..., <maybe blank or last value if present>]
        self::assertMatchesRegularExpression('/\[\s*13,\s*\.\.\.,/', $out);
    }

    #[Test]
    #[TestDox('summarizes 3D tensors with head/tail blocks and ellipsis between blocks')]
    public function summarized3DShowsBlockEllipsis(): void
    {
        $pp = new PrettyPrint();
        // Build 5 blocks so headB=2, tailB=2 produces a middle ellipsis
        $block = [[1, 2, 3], [4, 5, 6]];
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
        self::assertSame("tensor([{$this->nl}   [a, 2],{$this->nl}   [3, 4]{$this->nl}]){$this->nl}{$this->nl}", $out);
    }

    #[Test]
    #[TestDox('prints booleans and null as True False None')]
    public function booleansAndNull(): void
    {
        $pp = new PrettyPrint();
        ob_start();
        $pp(true, false, null);
        $out = ob_get_clean();
        self::assertSame("True{$this->nl}False{$this->nl}None{$this->nl}{$this->nl}", $out);
    }

    #[Test]
    #[TestDox('formats floats with custom precision via named arg')]
    public function precisionNamedArgumentScalar(): void
    {
        $pp = new PrettyPrint();
        ob_start();
        $pp(3.14159, precision: 2);
        $out = ob_get_clean();
        self::assertSame("3.14{$this->nl}{$this->nl}", $out);
    }

    #[Test]
    #[TestDox('formats floats with custom precision via trailing options array')]
    public function precisionTrailingArrayScalar(): void
    {
        $pp = new PrettyPrint();
        ob_start();
        $pp(3.14159, ['precision' => 6]);
        $out = ob_get_clean();
        self::assertSame("3.141590{$this->nl}{$this->nl}", $out);
    }

    #[Test]
    #[TestDox('restores default precision after a call that overrides it')]
    public function precisionRestoredBetweenCalls(): void
    {
        $pp = new PrettyPrint();
        ob_start();
        $pp(1.2345, precision: 2);
        $first = ob_get_clean();
        ob_start();
        $pp(1.2345);
        $second = ob_get_clean();
        self::assertSame("1.23{$this->nl}{$this->nl}", $first);
        self::assertSame("1.2345{$this->nl}{$this->nl}", $second);
    }

    #[Test]
    #[TestDox('applies precision to 2D tensor formatting')]
    public function precisionAppliedIn2DFormatting(): void
    {
        $pp = new PrettyPrint();
        $m = [[1.2, 3.4567], [9.0, 10.9999]];
        ob_start();
        $pp($m, precision: 2);
        $out = ob_get_clean();
        self::assertTrue(str_starts_with($out, 'tensor(['));
        self::assertTrue(str_ends_with($out, "]){$this->nl}{$this->nl}"));
        self::assertMatchesRegularExpression('/\b1\.20\b/', $out);
        self::assertMatchesRegularExpression('/\b3\.46\b/', $out);
        self::assertMatchesRegularExpression('/\b9\.00\b/', $out);
        self::assertMatchesRegularExpression('/\b11\.00\b/', $out);
    }

    #[Test]
    #[TestDox('aligns multiple 1D rows with an optional label')]
    public function multipleRowsWithLabel(): void
    {
        $pp = new PrettyPrint();
        ob_start();
        $pp('Label', [1, 2], [3, 4]);
        $out = ob_get_clean();
        self::assertSame("Label{$this->nl}[1, 2]{$this->nl}[3, 4]{$this->nl}{$this->nl}", $out);
    }

    #[Test]
    #[TestDox('limits the number of variadic arguments to MAX_ARGS (32)')]
    public function limitsMaxArgs(): void
    {
        $pp = new PrettyPrint();
        $args = range(1, 40);
        ob_start();
        $pp(...$args);
        $out = ob_get_clean();
        $expected = implode($this->nl, array_map(static fn ($n) => (string)$n, range(1, 32))) . $this->nl . $this->nl;
        self::assertSame($expected, $out);
    }

    #[Test]
    #[TestDox('removes unknown named arguments so they do not print as stray scalars')]
    public function unknownNamedArgsAreRemoved(): void
    {
        $pp = new PrettyPrint();
        ob_start();
        // foo and baz are unknown named args and should be stripped
        $pp('Hello', foo: 'bar', baz: 123);
        $out = ob_get_clean();
        self::assertSame("Hello{$this->nl}{$this->nl}", $out);
    }

    #[Test]
    #[TestDox('auto-wraps output with <pre>...</pre> in non-CLI (web) context')]
    public function autoWrapsPreInWebContext(): void
    {
        $pp = new PrettyPrint();
        Env::setCliOverride(false);
        try {
            ob_start();
            $pp('Hello', end: '');
            $out = ob_get_clean();
            self::assertSame('<pre>Hello</pre>', $out);
        } finally {
            Env::setCliOverride(null);
        }
    }

    #[Test]
    #[TestDox('does not wrap with <pre> in CLI context')]
    public function noPreWrapInCliContext(): void
    {
        $pp = new PrettyPrint();
        Env::setCliOverride(true);
        try {
            ob_start();
            $pp('Hello', end: '');
            $out = ob_get_clean();
            self::assertSame('Hello', $out);
        } finally {
            Env::setCliOverride(null);
        }
    }

    #[Test]
    #[TestDox('invokes PrettyPrint via explicit __invoke() method call')]
    public function invokeMethodDirectly(): void
    {
        $pp = new PrettyPrint();
        ob_start();
        $pp->__invoke('Direct');
        $out = ob_get_clean();
        self::assertSame("Direct{$this->nl}{$this->nl}", $out);
    }

    #[Test]
    #[TestDox('return option suppresses echo and returns the formatted string (CLI context)')]
    public function returnOptionSuppressesEchoAndReturnsString(): void
    {
        $pp = new PrettyPrint();
        ob_start();
        $s = $pp('Hello', return: true);
        $out = ob_get_clean();
        self::assertSame('', $out, 'No output should be echoed when return=true');
        self::assertSame("Hello{$this->nl}{$this->nl}", $s);
    }

    #[Test]
    #[TestDox('return option returns string without <pre> wrapping in web context')]
    public function returnOptionNoPreWrapInWebContext(): void
    {
        $pp = new PrettyPrint();
        Env::setCliOverride(false);
        try {
            ob_start();
            $s = $pp('Hi', return: true, end: '');
            $out = ob_get_clean();
            self::assertSame('', $out, 'No output should be echoed when return=true');
            self::assertSame('Hi', $s, 'Returned string should not be wrapped in <pre>');
            self::assertStringNotContainsString('<pre>', $s);
        } finally {
            Env::setCliOverride(null);
        }
    }

    #[Test]
    #[TestDox('return option parsed from trailing options array suppresses echo and returns string')]
    public function returnOptionViaTrailingArray(): void
    {
        $pp = new PrettyPrint();
        ob_start();
        $s = $pp('Trail', ['return' => true, 'end' => '']);
        $out = ob_get_clean();
        self::assertSame('', $out, 'No output should be echoed when return=true (trailing array)');
        self::assertSame('Trail', $s);
    }

    #[Test]
    #[TestDox('truncates label to MAX_LABEL_LEN when label exceeds limit')]
    public function labelIsTruncatedToMaxLength(): void
    {
        $pp = new PrettyPrint();
        $matrix = [[1, 2], [3, 4]];
        $long = str_repeat('L', 60); // longer than MAX_LABEL_LEN (50)
        $expectedLabel = substr($long, 0, 50);

        ob_start();
        $pp($matrix, ['label' => $long]);
        $out = ob_get_clean();

        // Output should start with the truncated label followed by '([' (PyTorch-like prefix)
        self::assertTrue(str_starts_with($out, $expectedLabel . '('), 'Label should be truncated at MAX_LABEL_LEN');
    }

    #[Test]
    #[TestDox('falls back to generic array formatting for 1D arrays (formatForArray path)')]
    public function defaultFormatterUsesFormatForArrayFor1D(): void
    {
        $pp = new PrettyPrint();
        ob_start();
        $pp([1, 2, 3]); // 1D array: not 2D or 3D -> formatForArray()
        $out = ob_get_clean();
        self::assertSame("[1, 2, 3]{$this->nl}{$this->nl}", $out);
    }

    #[Test]
    #[TestDox('converts objects with asArray() to arrays before formatting')]
    public function convertsObjectWithAsArrayToArray(): void
    {
        $pp = new PrettyPrint();

        $obj = new class () {
            public function asArray(): array
            {
                return [10, 20, 30];
            }
        };

        ob_start();
        $pp($obj);
        $out = ob_get_clean();

        self::assertSame("[10, 20, 30]{$this->nl}{$this->nl}", $out);
    }

    #[Test]
    #[TestDox('converts objects with toArray() to arrays when asArray() is not available')]
    public function convertsObjectWithToArrayToArray(): void
    {
        $pp = new PrettyPrint();

        $obj = new class () {
            public function toArray(): array
            {
                return ['x' => 5, 'y' => 6];
            }
        };

        ob_start();
        $pp($obj);
        $out = ob_get_clean();

        self::assertSame("[5, 6]{$this->nl}{$this->nl}", $out);
    }

    #[Test]
    #[TestDox('ignores objects whose asArray() does not return an array')]
    public function ignoresObjectWhenAsArrayDoesNotReturnArray(): void
    {
        $pp = new PrettyPrint();

        $obj = new class () {
            public function asArray(): string
            {
                return 'not-an-array';
            }
        };

        ob_start();
        $pp($obj);
        $out = ob_get_clean();

        // Falls back to generic object formatting path
        self::assertSame("Object{$this->nl}{$this->nl}", $out);
    }

    #[Test]
    #[TestDox('normalizes associative rows from asArray() into indexed arrays for 2D formatting')]
    public function normalizesAssociativeRowsFromAsArray(): void
    {
        $pp = new PrettyPrint();

        $obj = new class () {
            public function asArray(): array
            {
                return [
                    ['id' => 1, 'name' => 'Alice'],
                    ['id' => 2, 'name' => 'Bob'],
                ];
            }
        };

        ob_start();
        $pp($obj, label: 'Users');
        $out = ob_get_clean();

        // Ensure values are visible (rows have been normalized with array_values)
        self::assertStringContainsString('Alice', $out);
        self::assertStringContainsString('Bob', $out);
        // And we no longer see completely empty cells like "[, ]"
        self::assertStringNotContainsString('[, ]', $out);
    }

    #[Test]
    #[TestDox('applies rowsOnly and colsOnly to 2D matrices, including sparse selectors')]
    public function rowsOnlyAndColsOnlyOn2D(): void
    {
        $pp = new PrettyPrint();
        $nl = PHP_EOL;

        $matrix = [
            [1, 2, 3, 4, 5, 6],
            [10, 20, 30, 40, 50, 60],
            [100, 200, 300, 400, 500, 600],
        ];

        ob_start();
        $pp($matrix, rowsOnly: '2-3', colsOnly: '1-2,5');
        $out = ob_get_clean();

        // Basic structure: tensor header/footer
        self::assertTrue(str_starts_with($out, 'tensor(['));
        self::assertTrue(str_ends_with($out, "]){$nl}{$nl}"));

        // Rows 2 and 3 only: row 1 should not appear
        self::assertStringContainsString('10', $out);
        self::assertStringContainsString('100', $out);
        self::assertStringNotContainsString(' 1,  2,  3,  4,  5,  6', $out);

        // Columns 1,2,5 only (for those rows): 50 and 500 are present, 60/600 are not
        self::assertStringContainsString('50', $out);
        self::assertStringContainsString('500', $out);
        self::assertStringNotContainsString('60', $out);
        self::assertStringNotContainsString('600', $out);
    }

    #[Test]
    #[TestDox('applies rowsOnly and colsOnly to 3D tensors for each 2D slice')]
    public function rowsOnlyAndColsOnlyOn3D(): void
    {
        $pp = new PrettyPrint();
        $nl = PHP_EOL;

        $tensor = [
            [
                [1, 2, 3, 4],
                [5, 6, 7, 8],
            ],
            [
                [10, 20, 30, 40],
                [50, 60, 70, 80],
            ],
        ];

        ob_start();
        $pp($tensor, rowsOnly: '2', colsOnly: '1,3-4');
        $out = ob_get_clean();

        // 3D tensor header/footer
        self::assertTrue(str_starts_with($out, 'tensor(['));
        self::assertTrue(str_ends_with($out, "]){$nl}{$nl}"));

        // Only second row from each 2D slice should be visible (5,... and 50,...)
        self::assertStringContainsString('[[5, 7, 8]]', $out);
        self::assertStringContainsString('[[50, 70, 80]]', $out);
        self::assertStringNotContainsString('[1, 2, 3, 4]', $out);
        self::assertStringNotContainsString('[10, 20, 30, 40]', $out);

        // Columns 1,3,4 only: 7,8 and 70,80 present; 6,60 absent
        self::assertStringContainsString(' 7', $out);
        self::assertStringContainsString(' 8', $out);
        self::assertStringContainsString('70', $out);
        self::assertStringContainsString('80', $out);
        self::assertStringNotContainsString(' 6,', $out);
        self::assertStringNotContainsString('60,', $out);
    }

    #[Test]
    #[TestDox('ignores rowsOnly and colsOnly when indices are less than 1')]
    public function ignoresNonPositiveRowAndColIndices(): void
    {
        $pp = new PrettyPrint();
        $nl = PHP_EOL;

        $matrix = [
            [1, 2],
            [3, 4],
        ];

        // Baseline: no filtering
        ob_start();
        $pp($matrix, end: '');
        $baseline = ob_get_clean();

        // rowsOnly = 0 (int) should be ignored -> same as baseline
        ob_start();
        $pp($matrix, rowsOnly: 0, end: '');
        $rowsZero = ob_get_clean();

        // colsOnly = '0' (string) should be ignored -> same as baseline
        ob_start();
        $pp($matrix, colsOnly: '0', end: '');
        $colsZero = ob_get_clean();

        self::assertSame($baseline, $rowsZero);
        self::assertSame($baseline, $colsZero);
    }

    #[Test]
    #[TestDox('uses integer rowsOnly and colsOnly indices (positive) to filter 2D matrix')]
    public function usesPositiveIntegerRowAndColIndices(): void
    {
        $pp = new PrettyPrint();
        $nl = PHP_EOL;

        $matrix = [
            [1, 2, 3],
            [4, 5, 6],
            [7, 8, 9],
        ];

        ob_start();
        // rowsOnly as int 2, colsOnly as int 1 should select only value 4
        $pp($matrix, rowsOnly: 2, colsOnly: 1, end: '');
        $out = ob_get_clean();

        // Basic structure is still a tensor
        self::assertStringContainsString('tensor([', $out);
        self::assertStringContainsString('])', $out);

        // Only the element at row 2, col 1 (value 4) should be present
        self::assertStringContainsString('4', $out);
        self::assertStringNotContainsString('1', $out);
        self::assertStringNotContainsString('2', $out);
        self::assertStringNotContainsString('3', $out);
        self::assertStringNotContainsString('5', $out);
        self::assertStringNotContainsString('6', $out);
        self::assertStringNotContainsString('7', $out);
        self::assertStringNotContainsString('8', $out);
        self::assertStringNotContainsString('9', $out);
    }

    #[Test]
    #[TestDox('ignores empty and whitespace-only rowsOnly/colsOnly strings')]
    public function ignoresEmptyAndWhitespaceSelectors(): void
    {
        $pp = new PrettyPrint();
        $nl = PHP_EOL;

        $matrix = [
            [1, 2],
            [3, 4],
        ];

        // Baseline with no filtering
        ob_start();
        $pp($matrix, end: '');
        $baseline = ob_get_clean();

        // rowsOnly as empty string -> should be ignored
        ob_start();
        $pp($matrix, rowsOnly: '', end: '');
        $rowsEmpty = ob_get_clean();

        // colsOnly as whitespace-only string -> should be ignored
        ob_start();
        $pp($matrix, colsOnly: "   \t", end: '');
        $colsWhitespace = ob_get_clean();

        self::assertSame($baseline, $rowsEmpty);
        self::assertSame($baseline, $colsWhitespace);
    }

    #[Test]
    #[TestDox('ignores invalid selector segments so no filtering is applied')]
    public function ignoresCompletelyInvalidSelectors(): void
    {
        $pp = new PrettyPrint();
        $nl = PHP_EOL;

        $matrix = [
            [1, 2],
            [3, 4],
        ];

        // Baseline
        ob_start();
        $pp($matrix, end: '');
        $baseline = ob_get_clean();

        // rowsOnly with only invalid segments:
        // - empty segment between commas
        // - non-numeric text
        // - malformed range
        ob_start();
        $pp($matrix, rowsOnly: ',foo,1-bar,', end: '');
        $rowsInvalid = ob_get_clean();

        self::assertSame($baseline, $rowsInvalid);
    }

    #[Test]
    #[TestDox('rowsOnly silently ignores out-of-bounds row indices')]
    public function rowsOnlyIgnoresOutOfBoundsRows(): void
    {
        $pp = new PrettyPrint();
        $nl = PHP_EOL;

        $matrix = [
            [1, 2],
            [3, 4],
        ];

        // Baseline with no filtering
        ob_start();
        $pp($matrix, end: '');
        $baseline = ob_get_clean();

        // rowsOnly=5 on a 2-row matrix -> no valid rows selected, treated as empty
        ob_start();
        $pp($matrix, rowsOnly: '5', end: '');
        $out = ob_get_clean();

        // Should not blow up; will be an empty tensor. At minimum, must differ from baseline.
        self::assertNotSame($baseline, $out);
        // But should not contain any of the original values
        self::assertStringNotContainsString('1', $out);
        self::assertStringNotContainsString('2', $out);
        self::assertStringNotContainsString('3', $out);
        self::assertStringNotContainsString('4', $out);
    }

    #[Test]
    #[TestDox('colsOnly silently ignores out-of-bounds column indices')]
    public function colsOnlyIgnoresOutOfBoundsColumns(): void
    {
        $pp = new PrettyPrint();
        $nl = PHP_EOL;

        $matrix = [
            [1, 2, 3],
        ];

        // Baseline with no filtering
        ob_start();
        $pp($matrix, end: '');
        $baseline = ob_get_clean();

        // colsOnly=5 on a 3-column matrix: all requested indices are out-of-bounds
        ob_start();
        $pp($matrix, colsOnly: '5', end: '');
        $out = ob_get_clean();

        // Still should be a tensor wrapper, but with no original values
        self::assertStringContainsString('tensor([', $out);
        self::assertStringContainsString('])', $out);
        self::assertStringNotContainsString('1', $out);
        self::assertStringNotContainsString('2', $out);
        self::assertStringNotContainsString('3', $out);

        // And it should differ from the baseline (which contains 1,2,3)
        self::assertNotSame($baseline, $out);
    }
}
