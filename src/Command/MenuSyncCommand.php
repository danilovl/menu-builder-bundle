<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Command;

use Danilovl\MenuBuilderBundle\Attribute\MenuItem as MenuItemAttribute;
use Danilovl\MenuBuilderBundle\Model\MenuItemInterface;
use Danilovl\MenuBuilderBundle\Service\{
    MenuAttributeScanner,
    MenuManager
};
use Symfony\Component\Console\Attribute\{
    AsCommand,
    Option
};
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'danilovl:menu-builder:sync',
    description: 'Sync menu items from #[MenuItem] attributes on controllers'
)]
readonly class MenuSyncCommand
{
    public function __construct(
        private MenuAttributeScanner $scanner,
        private MenuManager $menus
    ) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Print changes without saving', name: 'dry-run')]
        bool $dryRun = false,
    ): int {
        $entries = $this->scanner->scan();
        if ($entries === []) {
            $io->note('No #[MenuItem] attributes found in controllers.');

            return Command::SUCCESS;
        }

        $created = 0;
        $skipped = 0;
        $rows = [];
        foreach ($entries as $entry) {
            $attribute = $entry['attribute'];
            $existing = $this->findExisting($attribute->menu, $attribute->label, $entry['route']);
            if ($existing !== null) {
                $skipped++;
                $rows[] = [$attribute->menu, $attribute->label, $entry['route'], 'skip (exists)'];

                continue;
            }

            $rows[] = [$attribute->menu, $attribute->label, $entry['route'], $dryRun ? 'would create' : 'create'];
            if ($dryRun) {
                continue;
            }

            $this->createFromAttribute($attribute, $entry['route']);
            $created++;
        }

        $io->table(['menu', 'label', 'route', 'action'], $rows);

        $message = sprintf('Created %d items, skipped %d.', $created, $skipped);
        $io->success($message);

        return Command::SUCCESS;
    }

    private function findExisting(string $menuName, string $label, ?string $route): ?MenuItemInterface
    {
        $items = $this->menus->getAllItems($menuName);
        foreach ($items as $item) {
            if ($this->matches($item, $label, $route)) {
                return $item;
            }

            $found = $this->findInChildren($item, $label, $route);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    private function findInChildren(MenuItemInterface $parent, string $label, ?string $route): ?MenuItemInterface
    {
        foreach ($parent->getChildren() as $child) {
            if ($this->matches($child, $label, $route)) {
                return $child;
            }

            $deeper = $this->findInChildren($child, $label, $route);
            if ($deeper !== null) {
                return $deeper;
            }
        }

        return null;
    }

    private function matches(MenuItemInterface $item, string $label, ?string $route): bool
    {
        if ($route !== null && $item->getRoute() === $route) {
            return true;
        }

        return $item->getLabel() === $label;
    }

    private function createFromAttribute(MenuItemAttribute $attribute, ?string $route): void
    {
        $payload = [
            'menuName' => $attribute->menu,
            'label' => $attribute->label,
            'route' => $route,
            'icon' => $attribute->icon,
            'iconImage' => $attribute->iconImage,
            'position' => $attribute->position,
            'type' => $attribute->type,
            'visibility' => $attribute->visibility,
            'requiredRoles' => $attribute->requiredRoles,
            'allowedUsers' => $attribute->allowedUsers,
            'cssClasses' => $attribute->cssClasses,
            'labelTranslated' => $attribute->labelTranslated,
            'translationDomain' => $attribute->translationDomain
        ];

        if ($attribute->parentLabel !== null) {
            $parent = $this->findExisting($attribute->menu, $attribute->parentLabel, null);
            if ($parent !== null) {
                $payload['parentId'] = $parent->getId();
            }
        }

        $this->menus->create($payload);
    }
}
