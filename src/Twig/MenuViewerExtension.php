<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Twig;

use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class MenuViewerExtension extends AbstractExtension
{
    private const string ENTRYPOINT_NAME = 'menu_builder';
    private const string BUNDLE_PUBLIC_PATH = 'bundles/menubuilder/build/';

    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
        private readonly Packages $packages
    ) {}

    /**
     * @return array<int, TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('danilovl_menu_builder_assets', $this->renderAssets(...), ['is_safe' => ['html']]),
        ];
    }

    public function renderAssets(): string
    {
        $entrypointsPath = $this->projectDir . '/public/' . self::BUNDLE_PUBLIC_PATH . 'entrypoints.json';
        if (!file_exists($entrypointsPath)) {
            return sprintf('<!-- MenuBuilderBundle: entrypoints.json not found at %s -->', $entrypointsPath);
        }

        $content = file_get_contents($entrypointsPath);
        if ($content === false) {
            return sprintf('<!-- MenuBuilderBundle: could not read %s -->', $entrypointsPath);
        }

        $entrypoints = json_decode($content, true);
        if (!is_array($entrypoints) || !isset($entrypoints['entrypoints']) || !is_array($entrypoints['entrypoints'])) {
            return sprintf('<!-- MenuBuilderBundle: entrypoints key not found or invalid in %s -->', $entrypointsPath);
        }

        /** @var array<string, mixed> $allEntrypoints */
        $allEntrypoints = $entrypoints['entrypoints'];
        if (!isset($allEntrypoints[self::ENTRYPOINT_NAME]) || !is_array($allEntrypoints[self::ENTRYPOINT_NAME])) {
            return sprintf(
                '<!-- MenuBuilderBundle: entrypoint "%s" not found in %s -->',
                self::ENTRYPOINT_NAME,
                $entrypointsPath,
            );
        }

        /** @var array{js?: array<int, string>, css?: array<int, string>} $assets */
        $assets = $allEntrypoints[self::ENTRYPOINT_NAME];
        $html = '';

        $jsAssets = $assets['js'] ?? [];
        foreach ($jsAssets as $js) {
            $url = $this->packages->getUrl($js);
            $html .= sprintf('<script src="%s" defer></script>', $url);
        }

        $cssAssets = $assets['css'] ?? [];
        foreach ($cssAssets as $css) {
            $url = $this->packages->getUrl($css);
            $html .= sprintf('<link rel="stylesheet" href="%s">', $url);
        }

        return $html;
    }
}
