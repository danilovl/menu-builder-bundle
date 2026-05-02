<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Twig;

use Danilovl\MenuBuilderBundle\DependencyInjection\MenuBuilderConfig;
use Danilovl\MenuBuilderBundle\Matcher\Matcher;
use Danilovl\MenuBuilderBundle\Model\MenuItemInterface;
use Danilovl\MenuBuilderBundle\Service\MenuManager;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\{
    Environment,
    TwigFunction
};
use Twig\Extension\AbstractExtension;

class MenuExtension extends AbstractExtension
{
    public function __construct(
        private readonly MenuManager $menus,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Matcher $matcher,
        private readonly MenuBuilderConfig $menuBuilderConfig,
        private readonly ?TranslatorInterface $translator = null
    ) {}

    /**
     * @return array<int, TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('danilovl_menu_builder_render', $this->render(...), ['is_safe' => ['html'], 'needs_environment' => true]),
            new TwigFunction('danilovl_menu_builder_tree', $this->tree(...)),
            new TwigFunction('danilovl_menu_builder_url', $this->url(...)),
            new TwigFunction('danilovl_menu_builder_label', $this->label(...)),
            new TwigFunction('danilovl_menu_builder_target', $this->target(...)),
            new TwigFunction('danilovl_menu_builder_is_active', $this->isActive(...)),
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    public function render(Environment $env, string $menuName, array $options = []): string
    {
        $defaultTemplate = $this->menuBuilderConfig->renderDefaultTemplate;
        $rawTemplate = $options['template'] ?? $defaultTemplate;
        $template = is_string($rawTemplate) ? $rawTemplate : $defaultTemplate;
        $tree = $this->menus->getTree($menuName);

        $context = [
            'items' => $tree,
            'options' => $options,
            'locale' => $options['locale'] ?? null,
            'current_route' => $options['current_route'] ?? null,
            'depth' => 0
        ];

        return $env->render($template, $context);
    }

    public function isActive(MenuItemInterface $item, ?string $currentRoute): bool
    {
        if ($this->matcher->isCurrent($item) || $this->matcher->isAncestor($item)) {
            return true;
        }

        if ($item->isActiveForRoute($currentRoute)) {
            return true;
        }

        foreach ($item->getChildren() as $child) {
            if ($this->isActive($child, $currentRoute)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return iterable<MenuItemInterface>
     */
    public function tree(string $menuName): iterable
    {
        return $this->menus->getTree($menuName);
    }

    public function url(MenuItemInterface $item, ?string $locale = null): string
    {
        $uri = $locale !== null ? $item->getUriForLocale($locale) : $item->getUri();
        if ($uri !== null && $uri !== '') {
            return $uri;
        }

        $route = $item->getRoute();
        if ($route === null || $route === '') {
            return '#';
        }

        return $this->urlGenerator->generate(
            name: $route,
            parameters: $item->getRouteParams()
        );
    }

    public function label(MenuItemInterface $item, ?string $locale = null): string
    {
        $raw = $locale !== null ? $item->getLabelForLocale($locale) : $item->getLabel();
        if (!$item->isLabelTranslated() || $this->translator === null) {
            return $raw;
        }

        return $this->translator->trans(
            id: $raw,
            domain: $item->getTranslationDomain(),
            locale: $locale,
        );
    }

    public function target(MenuItemInterface $item): ?string
    {
        $target = $item->getTarget();
        if ($target !== null && $target !== '') {
            return $target;
        }

        $attrTarget = $item->getAttributes()['target'] ?? null;
        if (!is_string($attrTarget) || $attrTarget === '') {
            return null;
        }

        return $attrTarget;
    }
}
