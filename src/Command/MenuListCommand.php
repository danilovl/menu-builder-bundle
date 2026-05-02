<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Command;

use Danilovl\MenuBuilderBundle\Service\MenuManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'danilovl:menu-builder:list', description: 'List all menus with item counts')]
readonly class MenuListCommand
{
    public function __construct(private MenuManager $menus) {}

    public function __invoke(SymfonyStyle $io): int
    {
        $names = $this->menus->getMenuNames();
        if ($names === []) {
            $io->note('No menus found.');

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($names as $name) {
            $items = $this->menus->getAllItems($name);
            $isActive = $this->menus->isMenuActive($name);
            $rows[] = [
                $name,
                count($items),
                $isActive ? 'yes' : 'no',
            ];
        }

        $io->table(['name', 'items', 'active'], $rows);

        return Command::SUCCESS;
    }
}
