<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Command;

use Danilovl\MenuBuilderBundle\Service\{
    MenuExporter,
    MenuImporter,
    MenuManager
};
use Symfony\Component\Console\Attribute\{
    Argument,
    AsCommand
};
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use DomainException;

#[AsCommand(
    name: 'danilovl:menu-builder:duplicate',
    description: 'Duplicate an entire menu under a new name'
)]
readonly class MenuDuplicateCommand
{
    public function __construct(
        private MenuManager $menus,
        private MenuExporter $exporter,
        private MenuImporter $importer
    ) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Source menu name')]
        string $source,
        #[Argument(description: 'Target menu name')]
        string $target,
    ): int {
        $existing = $this->menus->getMenuNames();
        if (!in_array($source, $existing, true)) {
            $message = sprintf('Source menu "%s" not found.', $source);
            $io->error($message);

            return Command::FAILURE;
        }
        if (in_array($target, $existing, true)) {
            $message = sprintf('Target menu "%s" already exists.', $target);
            $io->error($message);

            return Command::FAILURE;
        }

        $payload = $this->exporter->export($source);

        try {
            $result = $this->importer->import($payload, $target);
        } catch (DomainException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $message = sprintf('Duplicated %d items from "%s" to "%s".', $result['created'], $source, $target);
        $io->success($message);

        return Command::SUCCESS;
    }
}
