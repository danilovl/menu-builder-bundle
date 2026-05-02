<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Tests\Unit\Model;

use Danilovl\MenuBuilderBundle\Model\MenuItemVisibility;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MenuItemVisibilityTest extends TestCase
{
    #[DataProvider('provideIsValidAcceptsKnownValuesCases')]
    public function testIsValidAcceptsKnownValues(string $value): void
    {
        $isValid = MenuItemVisibility::isValid($value);

        $this->assertTrue($isValid);
    }

    public static function provideIsValidAcceptsKnownValuesCases(): Generator
    {
        yield 'always' => ['always'];
        yield 'authenticated' => ['authenticated'];
        yield 'anonymous' => ['anonymous'];
    }

    #[DataProvider('provideIsValidRejectsUnknownValuesCases')]
    public function testIsValidRejectsUnknownValues(string $value): void
    {
        $isValid = MenuItemVisibility::isValid($value);

        $this->assertFalse($isValid);
    }

    public static function provideIsValidRejectsUnknownValuesCases(): Generator
    {
        yield 'empty' => [''];
        yield 'unknown' => ['private'];
        yield 'mixed case' => ['Always'];
    }

    public function testTryFromReturnsEnum(): void
    {
        $resolved = MenuItemVisibility::tryFrom('authenticated');

        $this->assertSame(MenuItemVisibility::AUTHENTICATED, $resolved);
    }

    public function testTryFromReturnsNullForUnknown(): void
    {
        $unknown = (string) random_int(1_000_000, 9_999_999);
        $resolved = MenuItemVisibility::tryFrom($unknown);

        $this->assertNull($resolved);
    }
}
