<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Tests\Unit\Model;

use Danilovl\MenuBuilderBundle\Model\MenuItemType;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MenuItemTypeTest extends TestCase
{
    #[DataProvider('provideIsValidAcceptsKnownValuesCases')]
    public function testIsValidAcceptsKnownValues(string $value): void
    {
        $isValid = MenuItemType::isValid($value);

        $this->assertTrue($isValid);
    }

    public static function provideIsValidAcceptsKnownValuesCases(): Generator
    {
        yield 'link' => ['link'];
        yield 'none' => ['none'];
        yield 'divider' => ['divider'];
        yield 'heading' => ['heading'];
        yield 'external' => ['external'];
        yield 'mega' => ['mega'];
    }

    #[DataProvider('provideIsValidRejectsUnknownValuesCases')]
    public function testIsValidRejectsUnknownValues(string $value): void
    {
        $isValid = MenuItemType::isValid($value);

        $this->assertFalse($isValid);
    }

    public static function provideIsValidRejectsUnknownValuesCases(): Generator
    {
        yield 'empty' => [''];
        yield 'unknown' => ['banner'];
        yield 'mixed case' => ['Link'];
        yield 'whitespace' => [' link '];
    }

    public function testTryFromReturnsEnum(): void
    {
        $resolved = MenuItemType::tryFrom('link');

        $this->assertSame(MenuItemType::LINK, $resolved);
    }

    public function testTryFromReturnsNullForUnknown(): void
    {
        $unknown = (string) random_int(1_000_000, 9_999_999);
        $resolved = MenuItemType::tryFrom($unknown);

        $this->assertNull($resolved);
    }
}
