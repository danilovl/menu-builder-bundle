<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Command;

use Danilovl\MenuBuilderBundle\Service\{
    MenuExporter,
    MenuManager
};
use RuntimeException;
use Symfony\Component\Console\Attribute\{
    Argument,
    AsCommand,
    Option
};
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'danilovl:menu-builder:export', description: 'Export a menu to JSON (stdout or file)')]
readonly class MenuExportCommand
{
    public function __construct(
        private MenuExporter $exporter,
        private MenuManager $menus
    ) {}

    public function __invoke(
        SymfonyStyle $io,
        OutputInterface $output,
        #[Argument(description: 'Menu name')]
        string $name,
        #[Option(description: 'Write to file instead of stdout', name: 'output', shortcut: 'o')]
        ?string $target = null,
    ): int {
        $existing = $this->menus->getMenuNames();
        if (!in_array($name, $existing, true)) {
            $message = sprintf('Menu "%s" not found.', $name);
            $io->error($message);

            return Command::FAILURE;
        }

        $payload = $this->exporter->export($name);
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Failed to encode menu payload to JSON: ' . json_last_error_msg());
        }

        if ($target !== null) {
            $bytes = file_put_contents($target, $json);
            if ($bytes === false) {
                $message = sprintf('Failed to write to %s', $target);
                $io->error($message);

                return Command::FAILURE;
            }

            $message = sprintf('Wrote %d bytes to %s', $bytes, $target);
            $io->success($message);

            return Command::SUCCESS;
        }

        $output->writeln($json);

        return Command::SUCCESS;
    }
}
