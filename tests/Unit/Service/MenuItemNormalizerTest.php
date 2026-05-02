<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Tests\Unit\Service;

use DateTimeImmutable;
use DateTimeInterface;
use Danilovl\MenuBuilderBundle\Model\DetachedMenuItem;
use Danilovl\MenuBuilderBundle\Service\MenuItemNormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class MenuItemNormalizerTest extends TestCase
{
    public function testNormalizeReturnsArrayWithExpectedKeys(): void
    {
        $item = DetachedMenuItem::create('main', 'Home');
        $item->setUri('/home');
        $item->setRoute('app_home');

        $normalizer = new MenuItemNormalizer;
        $result = $normalizer->normalize($item);

        $this->assertSame('Home', $result['label']);
        $this->assertSame('/home', $result['uri']);
        $this->assertSame('app_home', $result['route']);
        $this->assertSame('main', $result['menuName']);
        $this->assertSame([], $result['children']);
        $this->assertFalse($result['hasChildren']);
        $this->assertNull($result['parentId']);
    }

    public function testNormalizeIncludesChildrenRecursively(): void
    {
        $parent = DetachedMenuItem::create('main', 'Parent');
        $child = DetachedMenuItem::create('main', 'Child');
        $parent->addChild($child);

        $normalizer = new MenuItemNormalizer;
        $result = $normalizer->normalize($parent);

        $this->assertCount(1, $result['children']);
        $this->assertTrue($result['hasChildren']);
        $firstChild = $result['children'][0];
        $this->assertSame('Child', $firstChild['label']);
    }

    public function testNormalizeUsesPreviewLocaleLabel(): void
    {
        $item = DetachedMenuItem::create('main', 'Home');
        $item->setTranslations(['ru' => ['label' => 'Home RU']]);

        $normalizer = new MenuItemNormalizer;
        $result = $normalizer->normalize($item, 'ru');

        $this->assertSame('Home RU', $result['resolvedLabel']);
    }

    public function testNormalizeFallsBackToDefaultLabelWithoutTranslation(): void
    {
        $item = DetachedMenuItem::create('main', 'Home');

        $normalizer = new MenuItemNormalizer;
        $result = $normalizer->normalize($item, 'ru');

        $this->assertSame('Home', $result['resolvedLabel']);
    }

    public function testNormalizeUsesTranslatorWhenLabelTranslatedFlagSet(): void
    {
        $item = DetachedMenuItem::create('main', 'menu.home');
        $item->setLabelTranslated(true);
        $item->setTranslationDomain('messages');

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->once())
            ->method('trans')
            ->with('menu.home', [], 'messages', 'en')
            ->willReturn('Home page');

        $normalizer = new MenuItemNormalizer($translator);
        $result = $normalizer->normalize($item, 'en');

        $this->assertSame('Home page', $result['resolvedLabel']);
    }

    public function testNormalizeSerializesPublishedAtAsAtom(): void
    {
        $item = DetachedMenuItem::create('main', 'Home');
        $publishedDate = new DateTimeImmutable('2024-06-01T10:00:00+00:00');
        $item->setPublishedAt($publishedDate);

        $normalizer = new MenuItemNormalizer;
        $result = $normalizer->normalize($item);

        $expected = $publishedDate->format(DateTimeInterface::ATOM);
        $this->assertSame($expected, $result['publishedAt']);
    }

    public function testNormalizeTreeCallsNormalizeForEveryRoot(): void
    {
        $first = DetachedMenuItem::create('main', 'First');
        $second = DetachedMenuItem::create('main', 'Second');
        $tree = [$first, $second];

        $normalizer = new MenuItemNormalizer;
        $result = $normalizer->normalizeTree($tree);

        $this->assertCount(2, $result);
        $this->assertSame('First', $result[0]['label']);
        $this->assertSame('Second', $result[1]['label']);
    }

    public function testNormalizeIncludesParentIdWhenAttached(): void
    {
        $parent = DetachedMenuItem::create('main', 'Parent');
        $child = DetachedMenuItem::create('main', 'Child');
        $parent->addChild($child);

        $normalizer = new MenuItemNormalizer;
        $result = $normalizer->normalize($child);

        $expectedParentId = $parent->getId();
        $this->assertSame($expectedParentId, $result['parentId']);
    }
}
