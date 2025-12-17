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

use function Apphp\PrettyPrint\{pprint, pp, ppd, pdiff};

#[Group('PrettyPrint')]
#[CoversFunction('Apphp\\PrettyPrint\\pprint')]
#[CoversFunction('Apphp\\PrettyPrint\\pp')]
#[CoversFunction('Apphp\\PrettyPrint\\ppd')]
#[CoversFunction('Apphp\\PrettyPrint\\pdiff')]
final class FunctionsTest extends TestCase
{
    private string $nl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->nl = PHP_EOL;
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
}
