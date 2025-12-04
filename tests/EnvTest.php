<?php

declare(strict_types=1);


use Apphp\PrettyPrint\Env;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

#[CoversClass(Env::class)]
#[Group('PrettyPrint')]
final class EnvTest extends TestCase
{
    #[Test]
    #[TestDox('isCli() matches current PHP_SAPI (cli/phpdbg => true, otherwise false)')]
    public function testIsCliMatchesCurrentSapi(): void
    {
        $expected = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
        self::assertSame($expected, Env::isCli());
    }

    #[Test]
    #[TestDox('isCli() returns a boolean')]
    public function testIsCliReturnsBool(): void
    {
        self::assertIsBool(Env::isCli());
    }

    #[Test]
    #[TestDox('setCliOverride() forces isCli() result and can be reset')]
    public function testSetCliOverrideForcesAndResets(): void
    {
        // Force non-CLI
        Env::setCliOverride(false);
        self::assertFalse(Env::isCli());

        // Force CLI
        Env::setCliOverride(true);
        self::assertTrue(Env::isCli());

        // Reset to auto-detect
        Env::setCliOverride(null);
        $expected = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
        self::assertSame($expected, Env::isCli());
    }
}
