<?php

declare(strict_types=1);

namespace Apphp\PrettyPrint\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversFunction;

#[Group('prettyprint')]
#[CoversFunction('pprint')]
#[CoversFunction('pp')]
#[CoversFunction('ppd')]
final class FunctionsTest extends TestCase
{
    #[Test]
    #[TestDox('global function pprint prints arguments using PrettyPrint')]
    public function pprintPrintsOutput(): void
    {
        ob_start();
        pprint('Hello');
        $out = ob_get_clean();
        self::assertSame("'Hello'\n", $out);
    }

    #[Test]
    #[TestDox('global function pp is an alias of pprint')]
    public function ppIsAlias(): void
    {
        ob_start();
        pp(1, 2, 3);
        $out = ob_get_clean();
        self::assertSame("1 2 3\n", $out);
    }
}
