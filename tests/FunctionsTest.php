<?php

declare(strict_types=1);

namespace Apphp\PrettyPrint\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use Apphp\PrettyPrint\Env;

use function Apphp\PrettyPrint\{pprint, pp, ppd, pdiff, pcompare};

#[Group('PrettyPrint')]
#[CoversFunction('Apphp\\PrettyPrint\\pprint')]
#[CoversFunction('Apphp\\PrettyPrint\\pp')]
#[CoversFunction('Apphp\\PrettyPrint\\ppd')]
#[CoversFunction('Apphp\\PrettyPrint\\pdiff')]
#[CoversFunction('Apphp\\PrettyPrint\\pcompare')]
final class FunctionsTest extends TestCase
{
    private string $nl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->nl = PHP_EOL;
        Env::setCliOverride(true);
    }

    protected function tearDown(): void
    {
        Env::setCliOverride(null);
        parent::tearDown();
    }

    #[Test]
    #[TestDox('global function pprint prints arguments using PrettyPrint')]
    public function pprintPrintsOutput(): void
    {
        ob_start();
        pprint('Hello');
        $out = ob_get_clean();
        self::assertSame("Hello{$this->nl}{$this->nl}", $out);
    }

    #[Test]
    #[TestDox('global function pp is an alias of pprint')]
    public function ppIsAlias(): void
    {
        ob_start();
        pp(1, 2, 3);
        $out = ob_get_clean();
        self::assertSame("1{$this->nl}2{$this->nl}3{$this->nl}{$this->nl}", $out);
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    #[TestDox('global function ppd prints output (no exit in test env)')]
    public function ppdPrintsThenExits(): void
    {
        $this->expectOutputString("hello{$this->nl}{$this->nl}");
        ppd('hello');
    }

    #[Test]
    #[TestDox('pprint uses asArray() when object provides it')]
    public function pprintUsesAsArrayOnObject(): void
    {
        $obj = new class () {
            public function asArray(): array
            {
                return [1, 2, 3];
            }
        };

        ob_start();
        pprint($obj);
        $out = ob_get_clean();

        self::assertSame("[1, 2, 3]{$this->nl}{$this->nl}", $out);
    }

    #[Test]
    #[TestDox('pprint falls back to toArray() when object has no asArray()')]
    public function pprintUsesToArrayOnObject(): void
    {
        $obj = new class () {
            public function toArray(): array
            {
                return ['a' => 1, 'b' => 2];
            }
        };

        ob_start();
        pprint($obj);
        $out = ob_get_clean();

        self::assertSame("[1, 2]{$this->nl}{$this->nl}", $out);
    }

    #[Test]
    #[TestDox('pdiff prints a matrix with identical values or x on mismatches')]
    public function pdiffPrintsDifferenceMatrix(): void
    {
        $a = [
            [1, 2, 3],
            [4, 5, 6],
        ];
        $b = [
            [1, 9, 3],
            [0, 5, 7],
        ];

        ob_start();
        pdiff($a, $b);
        $out = ob_get_clean();

        // Expect 1st row: [1, x, 3]
        self::assertMatchesRegularExpression('/\[\s*1,\s*-,\s*3\s*\]/', $out);
        // Expect 2nd row: [x, 5, x]
        self::assertMatchesRegularExpression('/\[\s*-,\s*5,\s*-\s*\]/', $out);
    }

    #[Test]
    #[TestDox('pdiff handles scalar-vs-scalar rows via the scalar branch')]
    public function pdiffHandlesScalarRows(): void
    {
        $a = [1, 2];
        $b = [1, 9];

        ob_start();
        pdiff($a, $b);
        $out = ob_get_clean();

        // First row: both scalars equal -> [1]
        self::assertMatchesRegularExpression('/\[\s*1\s*\]/', $out);
        // Second row: scalars differ -> [-]
        self::assertMatchesRegularExpression('/\[\s*-\s*\]/', $out);
    }

    #[Test]
    #[TestDox('pcompare prints two matrices and colors same elements green and different elements red in CLI')]
    public function pcompareCliColorsCorrectly(): void
    {
        Env::setCliOverride(true);

        $a = [
            [1, 2],
            [3, 4],
        ];
        $b = [
            [1, 9],
            [0, 4],
        ];

        $out = pcompare($a, $b, ['return' => true, 'end' => ""]);

        // Green for equal (1 and 4), red for different (2,3) and (9,0)
        self::assertStringContainsString("\033[32m", $out);
        self::assertStringContainsString("\033[31m", $out);

        // Ensure it prints two matrices (separated by a blank line)
        self::assertMatchesRegularExpression('/\]\R\R\[/', $out);
    }

    #[Test]
    #[TestDox('pcompare uses HTML <pre> and <span> coloring when not in CLI')]
    public function pcompareHtmlColorsCorrectly(): void
    {
        Env::setCliOverride(false);

        $a = [[1]];
        $b = [[2]];

        $out = pcompare($a, $b, ['return' => true, 'end' => '']);

        self::assertStringContainsString('<pre>', $out);
        self::assertStringContainsString('</pre>', $out);
        self::assertStringContainsString('<span style="color: red">', $out);

        Env::setCliOverride(true);
    }

    #[Test]
    #[TestDox('pcompare returns empty matrices placeholder when both inputs are empty (HTML mode)')]
    public function pcompareHandlesEmptyMatricesInHtmlMode(): void
    {
        Env::setCliOverride(false);

        try {
            $out = pcompare([], [], ['return' => true, 'end' => '']);

            // Expect HTML <pre> wrapper with two empty matrix lines
            self::assertSame('<pre>[]' . $this->nl . $this->nl . '[]</pre>', $out);
        } finally {
            Env::setCliOverride(true);
        }
    }

    #[Test]
    #[TestDox('pcompare respects precision option when formatting cell values')]
    public function pcompareRespectsPrecisionOption(): void
    {
        // Force CLI mode so pcompare uses ANSI formatting, not HTML
        Env::setCliOverride(true);

        $a = [[1.2345]];
        $b = [[1.2345]];

        $out = pcompare($a, $b, [
            'precision' => 1,
            'return'    => true,
            'end'       => '',
        ]);

        // With precision=1 we expect values like 1.2 (not 1.23 or 1.2345)
        self::assertStringContainsString('1.2', $out);
        self::assertStringNotContainsString('1.23', $out);
        self::assertStringNotContainsString('1.2345', $out);
    }

    #[Test]
    #[TestDox('pcompare respects start option when building output prefix')]
    public function pcompareRespectsStartOption(): void
    {
        // Use CLI mode so we avoid <pre> wrapping and keep assertions simple
        Env::setCliOverride(true);

        $a = [
            [1, 2],
        ];
        $b = [
            [1, 2],
        ];

        $out = pcompare($a, $b, [
            'start'  => '>> ',
            'return' => true,
            'end'    => '',
        ]);

        // Output should start with the custom prefix
        self::assertStringStartsWith('>> ', $out);
    }

    #[Test]
    #[TestDox('pcompare echoes output when return option is not set')]
    public function pcompareEchoesWhenReturnIsFalse(): void
    {
        Env::setCliOverride(true);

        $a = [[1, 2]];
        $b = [[1, 3]];

        ob_start();
        // No 'return' => true, so pcompare should echo
        pcompare($a, $b, ['end' => '']);
        $out = ob_get_clean();

        self::assertNotSame('', $out);
        self::assertStringContainsString('1', $out);
        self::assertStringContainsString('2', $out);
        self::assertStringContainsString('3', $out);
    }

    #[Test]
    #[TestDox('pcompare echoes empty matrices placeholder in CLI mode when return is false')]
    public function pcompareEchoesEmptyMatricesInCliMode(): void
    {
        // Force CLI mode so no <pre> wrapping is used
        Env::setCliOverride(true);

        ob_start();
        // No 'return' => true, so pcompare should echo from the cols===0 branch
        pcompare([], [], ['end' => '']);
        $out = ob_get_clean();

        // Expect two empty matrix lines in CLI (no <pre> wrapping)
        self::assertSame('[]' . $this->nl . $this->nl . '[]', $out);
    }
}
