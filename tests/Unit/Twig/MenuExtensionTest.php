<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Tests\Unit\Twig;

use Danilovl\MenuBuilderBundle\DependencyInjection\MenuBuilderConfig;
use Danilovl\MenuBuilderBundle\Matcher\Matcher;
use Danilovl\MenuBuilderBundle\Model\DetachedMenuItem;
use Danilovl\MenuBuilderBundle\Service\MenuManager;
use Danilovl\MenuBuilderBundle\Twig\MenuExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Exception\{
    MissingMandatoryParametersException,
    RouteNotFoundException
};
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Throwable;

final class MenuExtensionTest extends TestCase
{
    private function makeExtension(?Throwable $generatorException = null): MenuExtension
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        if ($generatorException !== null) {
            $urlGenerator->method('generate')->willThrowException($generatorException);
        } else {
            $urlGenerator->method('generate')->willReturn('/generated');
        }

        $config = new MenuBuilderConfig(
            storageDriver: 'mock',
            storageRedisDsn: null,
            storageTtl: null,
            storageKeyPrefix: 'menu_builder.',
            storagePool: 'cache.app',
            softDelete: true,
            apiPrefix: '/api/menu',
            apiPublicCacheTtl: 0,
            routeCatalogPreloadLimit: 500,
            renderDefaultTemplate: '@MenuBuilder/menu/default.html.twig',
        );

        $menuManager = $this->createStub(MenuManager::class);
        $matcher = new Matcher;

        return new MenuExtension($menuManager, $urlGenerator, $matcher, $config);
    }

    public function testSafeUriIsReturned(): void
    {
        $item = DetachedMenuItem::create('main', 'Home');
        $item->setUri('https://example.com');
        $extension = $this->makeExtension();

        $url = $extension->url($item);

        $this->assertSame('https://example.com', $url);
    }

    public function testUnsafeUriIsReplacedWithHash(): void
    {
        $item = DetachedMenuItem::create('main', 'XSS');
        $item->setUri("java\tscript:alert(1)");
        $extension = $this->makeExtension();

        $url = $extension->url($item);

        $this->assertSame('#', $url);
    }

    public function testUnsafeLocalizedUriIsReplacedWithHash(): void
    {
        $item = DetachedMenuItem::create('main', 'XSS');
        $item->setUri('/safe');
        $item->setTranslations(['cs' => ['uri' => 'javascript:alert(1)']]);
        $extension = $this->makeExtension();

        $localizedUrl = $extension->url($item, 'cs');
        $defaultUrl = $extension->url($item);

        $this->assertSame('#', $localizedUrl);
        $this->assertSame('/safe', $defaultUrl);
    }

    public function testRouteIsGenerated(): void
    {
        $item = DetachedMenuItem::create('main', 'Route');
        $item->setRoute('app_home');
        $extension = $this->makeExtension();

        $url = $extension->url($item);

        $this->assertSame('/generated', $url);
    }

    public function testMissingRouteFallsBackToHash(): void
    {
        $item = DetachedMenuItem::create('main', 'Route');
        $item->setRoute('app_removed');
        $exception = new RouteNotFoundException;
        $extension = $this->makeExtension($exception);

        $url = $extension->url($item);

        $this->assertSame('#', $url);
    }

    public function testMissingRouteParametersFallBackToHash(): void
    {
        $item = DetachedMenuItem::create('main', 'Route');
        $item->setRoute('app_show');
        $exception = new MissingMandatoryParametersException('app_show', ['id']);
        $extension = $this->makeExtension($exception);

        $url = $extension->url($item);

        $this->assertSame('#', $url);
    }

    public function testItemWithoutUriAndRouteReturnsHash(): void
    {
        $item = DetachedMenuItem::create('main', 'Heading');
        $extension = $this->makeExtension();

        $url = $extension->url($item);

        $this->assertSame('#', $url);
    }
}
