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
        Env::setCliOverride(false);
        self::assertFalse(Env::isCli());
        Env::setCliOverride(true);
        self::assertTrue(Env::isCli());
        Env::setCliOverride(null);
        $expected = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
        self::assertSame($expected, Env::isCli());
    }

    #[Test]
    #[TestDox('isTest() returns true when APP_ENV=test under CLI')]
    public function testIsTestTrueWhenAppEnvTest(): void
    {
        $prev = getenv('APP_ENV');
        try {
            putenv('APP_ENV=test');
            self::assertSame(PHP_SAPI === 'cli', Env::isTest());
        } finally {
            putenv('APP_ENV' . ($prev === false ? '' : '=' . $prev));
        }
    }

    #[Test]
    #[TestDox('isTest() returns false when APP_ENV is not test')]
    public function testIsTestFalseWhenAppEnvNotTest(): void
    {
        $prev = getenv('APP_ENV');
        try {
            putenv('APP_ENV=dev');
            self::assertFalse(Env::isTest());
        } finally {
            putenv('APP_ENV' . ($prev === false ? '' : '=' . $prev));
        }
    }
}
