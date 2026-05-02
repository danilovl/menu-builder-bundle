<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Command;

use Danilovl\MenuBuilderBundle\Model\MenuItemInterface;
use Danilovl\MenuBuilderBundle\Service\{
    MenuManager,
    RouteExistenceChecker
};
use Symfony\Component\Console\Attribute\{
    Argument,
    AsCommand
};
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'danilovl:menu-builder:validate-routes',
    description: 'Find menu items referencing non-existent Symfony routes'
)]
readonly class MenuValidateRoutesCommand
{
    public function __construct(
        private MenuManager $menus,
        private RouteExistenceChecker $routeChecker
    ) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Menu name (default: all menus)')]
        ?string $name = null,
    ): int {
        $names = is_string($name) && $name !== ''
            ? [$name]
            : $this->menus->getMenuNames();

        $broken = [];
        foreach ($names as $menuName) {
            $items = $this->menus->getAllItems($menuName);
            $this->scan($items, $menuName, $broken);
        }

        if ($broken === []) {
            $io->success('All menu items reference valid routes.');

            return Command::SUCCESS;
        }

        $io->table(['menu', 'item', 'route'], $broken);
        $message = sprintf('%d items reference missing routes.', count($broken));
        $io->warning($message);

        return Command::FAILURE;
    }

    /**
     * @param array<int, MenuItemInterface> $items
     * @param array<int, array{0: string, 1: string, 2: string}> $broken
     */
    private function scan(array $items, string $menuName, array &$broken): void
    {
        foreach ($items as $item) {
            $route = $item->getRoute();
            if ($route !== null && $route !== '' && !$this->routeChecker->exists($route)) {
                $broken[] = [$menuName, $item->getLabel(), $route];
            }

            $children = iterator_to_array($item->getChildren());
            $this->scan($children, $menuName, $broken);
        }
    }
}
