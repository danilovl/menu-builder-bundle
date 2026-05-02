<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Tests\Unit\Model;

use DateTimeImmutable;
use Danilovl\MenuBuilderBundle\Model\{
    DetachedMenuItem,
    MenuItemVisibility
};
use Generator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DetachedMenuItemTest extends TestCase
{
    public function testCreateGeneratesUuidAndAssignsBasicFields(): void
    {
        $item = DetachedMenuItem::create('main', 'Home');

        $id = $item->getId();
        $menuName = $item->getMenuName();
        $label = $item->getLabel();

        $this->assertNotSame('', $id);
        $this->assertSame('main', $menuName);
        $this->assertSame('Home', $label);
    }

    public function testCreateProducesUniqueIds(): void
    {
        $first = DetachedMenuItem::create('main', 'A');
        $second = DetachedMenuItem::create('main', 'B');

        $firstId = $first->getId();
        $secondId = $second->getId();

        $this->assertNotSame($firstId, $secondId);
    }

    public function testDefaultStateIsActiveAndDisplayed(): void
    {
        $item = DetachedMenuItem::create('main', 'Home');

        $isActive = $item->isActive();
        $isDisplayed = $item->isDisplayed();

        $this->assertTrue($isActive);
        $this->assertTrue($isDisplayed);
    }

    public function testDefaultVisibilityIsAlways(): void
    {
        $item = DetachedMenuItem::create('main', 'Home');

        $visibility = $item->getVisibilityEnum();

        $this->assertSame(MenuItemVisibility::ALWAYS, $visibility);
    }

    public function testDefaultTypeIsLink(): void
    {
        $item = DetachedMenuItem::create('main', 'Home');

        $type = $item->getType();

        $this->assertSame('link', $type);
    }

    public function testSetIconImageEmptyStringNormalizesToNull(): void
    {
        $item = DetachedMenuItem::create('main', 'Home');
        $item->setIconImage('');

        $iconImage = $item->getIconImage();

        $this->assertNull($iconImage);
    }

    public function testSetTargetEmptyStringNormalizesToNull(): void
    {
        $item = DetachedMenuItem::create('main', 'Home');
        $item->setTarget('');

        $target = $item->getTarget();

        $this->assertNull($target);
    }

    public function testSetTranslationDomainEmptyStringNormalizesToNull(): void
    {
        $item = DetachedMenuItem::create('main', 'Home');
        $item->setTranslationDomain('');

        $domain = $item->getTranslationDomain();

        $this->assertNull($domain);
    }

    public function testSetVisibilityFromString(): void
    {
        $item = DetachedMenuItem::create('main', 'Home');
        $item->setVisibility('authenticated');

        $visibility = $item->getVisibilityEnum();

        $this->assertSame(MenuItemVisibility::AUTHENTICATED, $visibility);
    }

    public function testSetVisibilityFromEnum(): void
    {
        $item = DetachedMenuItem::create('main', 'Home');
        $item->setVisibility(MenuItemVisibility::ANONYMOUS);

        $visibility = $item->getVisibilityEnum();

        $this->assertSame(MenuItemVisibility::ANONYMOUS, $visibility);
    }

    public function testSetVisibilityRejectsInvalidString(): void
    {
        $item = DetachedMenuItem::create('main', 'Home');

        $this->expectException(InvalidArgumentException::class);
        $item->setVisibility('private');
    }

    public function testSetTypeRejectsInvalidString(): void
    {
        $item = DetachedMenuItem::create('main', 'Home');

        $this->expectException(InvalidArgumentException::class);
        $item->setType('banner');
    }

    public function testSetColumnClampsNegativeToZero(): void
    {
        $item = DetachedMenuItem::create('main', 'Home');
        $item->setColumn(-5);

        $column = $item->getColumn();

        $this->assertSame(0, $column);
    }

    public function testSetTranslationsKeepsValidEntriesOnly(): void
    {
        $item = DetachedMenuItem::create('main', 'Home');
        $payload = [
            'en' => ['label' => 'Home', 'uri' => '/home'],
            'ru' => ['label' => 'Home RU'],
            '' => ['label' => 'Empty locale'],
            'de' => ['label' => '', 'uri' => ''],
            'fr' => ['something' => 'else'],
        ];
        $item->setTranslations($payload);

        $translations = $item->getTranslations();

        $expected = [
            'en' => ['label' => 'Home', 'uri' => '/home'],
            'ru' => ['label' => 'Home RU'],
        ];
        $this->assertSame($expected, $translations);
    }

    public function testGetLabelForLocaleFallsBackToDefault(): void
    {
        $item = DetachedMenuItem::create('main', 'Home');
        $item->setTranslations(['ru' => ['label' => 'Home RU']]);

        $russian = $item->getLabelForLocale('ru');
        $german = $item->getLabelForLocale('de');

        $this->assertSame('Home RU', $russian);
        $this->assertSame('Home', $german);
    }

    public function testGetUriForLocaleFallsBackToDefault(): void
    {
        $item = DetachedMenuItem::create('main', 'Home');
        $item->setUri('/home');
        $item->setTranslations(['ru' => ['uri' => '/glavnaya']]);

        $russian = $item->getUriForLocale('ru');
        $german = $item->getUriForLocale('de');

        $this->assertSame('/glavnaya', $russian);
        $this->assertSame('/home', $german);
    }

    public function testSetDependentActiveRoutesDeduplicatesAndDropsEmpty(): void
    {
        $item = DetachedMenuItem::create('main', 'Home');
        $input = ['app_home', '', 'app_about', 'app_home', 'app_contact'];
        $item->setDependentActiveRoutes($input);

        $routes = $item->getDependentActiveRoutes();

        $this->assertSame(['app_home', 'app_about', 'app_contact'], $routes);
    }

    public function testSetCssClassesTrimsAndDeduplicates(): void
    {
        $item = DetachedMenuItem::create('main', 'Home');
        $input = ['  primary  ', 'primary', '', 'highlighted', '   '];
        $item->setCssClasses($input);

        $classes = $item->getCssClasses();

        $this->assertSame(['primary', 'highlighted'], $classes);
    }

    /**
     * @param array<int, string> $dependents
     */
    #[DataProvider('provideIsActiveForRouteCases')]
    public function testIsActiveForRoute(?string $current, string $itemRoute, array $dependents, bool $expected): void
    {
        $item = DetachedMenuItem::create('main', 'Home');
        $item->setRoute($itemRoute);
        $item->setDependentActiveRoutes($dependents);

        $result = $item->isActiveForRoute($current);

        $this->assertSame($expected, $result);
    }

    public static function provideIsActiveForRouteCases(): Generator
    {
        yield 'null current' => [null, 'app_home', [], false];
        yield 'empty current' => ['', 'app_home', [], false];
        yield 'direct match' => ['app_home', 'app_home', [], true];
        yield 'dependent match' => ['app_blog_show', 'app_home', ['app_blog_show'], true];
        yield 'no match' => ['app_other', 'app_home', ['app_blog'], false];
    }

    #[DataProvider('provideIsPublishedAtChecksWindowCases')]
    public function testIsPublishedAtChecksWindow(
        ?string $publishedAt,
        ?string $unpublishedAt,
        string $now,
        bool $expected,
    ): void {
        $item = DetachedMenuItem::create('main', 'Home');

        if ($publishedAt !== null) {
            $publishedDate = new DateTimeImmutable($publishedAt);
            $item->setPublishedAt($publishedDate);
        }
        if ($unpublishedAt !== null) {
            $unpublishedDate = new DateTimeImmutable($unpublishedAt);
            $item->setUnpublishedAt($unpublishedDate);
        }

        $reference = new DateTimeImmutable($now);
        $result = $item->isPublishedAt($reference);

        $this->assertSame($expected, $result);
    }

    public static function provideIsPublishedAtChecksWindowCases(): Generator
    {
        yield 'no window' => [null, null, '2024-06-01', true];
        yield 'before window' => ['2024-07-01', null, '2024-06-01', false];
        yield 'inside window' => ['2024-05-01', '2024-07-01', '2024-06-01', true];
        yield 'at unpublish boundary' => [null, '2024-06-01', '2024-06-01', false];
        yield 'after unpublish' => [null, '2024-06-01', '2024-07-01', false];
        yield 'only published past' => ['2024-05-01', null, '2024-06-01', true];
    }

    public function testAddChildSetsParentReference(): void
    {
        $parent = DetachedMenuItem::create('main', 'Parent');
        $child = DetachedMenuItem::create('main', 'Child');
        $parent->addChild($child);

        $resolvedParent = $child->getParent();
        $hasChildren = $parent->hasChildren();

        $this->assertSame($parent, $resolvedParent);
        $this->assertTrue($hasChildren);
    }

    public function testRemoveChildDropsByIdAndKeepsOthers(): void
    {
        $parent = DetachedMenuItem::create('main', 'Parent');
        $childA = DetachedMenuItem::create('main', 'A');
        $childB = DetachedMenuItem::create('main', 'B');

        $parent->addChild($childA);
        $parent->addChild($childB);
        $parent->removeChild($childA);

        $childrenIterable = $parent->getChildren();
        $children = iterator_to_array($childrenIterable);

        $this->assertCount(1, $children);
        $this->assertSame($childB, $children[0]);
    }

    public function testHasChildrenReflectsInternalState(): void
    {
        $parent = DetachedMenuItem::create('main', 'Parent');
        $child = DetachedMenuItem::create('main', 'Child');

        $beforeAdd = $parent->hasChildren();
        $parent->addChild($child);
        $afterAdd = $parent->hasChildren();

        $this->assertFalse($beforeAdd);
        $this->assertTrue($afterAdd);
    }

    public function testToArrayContainsAllPersistableFields(): void
    {
        $item = DetachedMenuItem::create('main', 'Home');
        $item->setUri('/home');
        $item->setRoute('app_home');
        $item->setRouteParams(['slug' => 'about']);
        $item->setIcon('fa-home');
        $item->setVisibility(MenuItemVisibility::AUTHENTICATED);
        $item->setType('mega');
        $item->setColumn(3);
        $item->setCssClasses(['primary']);

        $array = $item->toArray();

        $this->assertSame('Home', $array['label']);
        $this->assertSame('/home', $array['uri']);
        $this->assertSame('app_home', $array['route']);
        $this->assertSame(['slug' => 'about'], $array['routeParams']);
        $this->assertSame('fa-home', $array['icon']);
        $this->assertSame('authenticated', $array['visibility']);
        $this->assertSame('mega', $array['type']);
        $this->assertSame(3, $array['column']);
        $this->assertSame(['primary'], $array['cssClasses']);
        $this->assertSame([], $array['children']);
    }

    public function testFromArrayRestoresCompleteState(): void
    {
        $original = DetachedMenuItem::create('main', 'Home');
        $original->setUri('/home');
        $original->setRoute('app_home');
        $original->setIcon('fa-home');
        $original->setVisibility(MenuItemVisibility::AUTHENTICATED);
        $original->setType('mega');
        $original->setColumn(2);
        $original->setCssClasses(['primary']);
        $original->setTranslations(['ru' => ['label' => 'Home RU']]);

        $serialized = $original->toArray();
        $restored = DetachedMenuItem::fromArray($serialized);

        $restoredArray = $restored->toArray();

        $this->assertSame($serialized, $restoredArray);
    }

    public function testFromArrayPreservesChildrenHierarchy(): void
    {
        $parent = DetachedMenuItem::create('main', 'Parent');
        $child = DetachedMenuItem::create('main', 'Child');
        $parent->addChild($child);

        $serialized = $parent->toArray();
        $restored = DetachedMenuItem::fromArray($serialized);

        $childrenIterable = $restored->getChildren();
        $children = iterator_to_array($childrenIterable);

        $this->assertCount(1, $children);
        $firstChildLabel = $children[0]->getLabel();
        $this->assertSame('Child', $firstChildLabel);
    }
}
