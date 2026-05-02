<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Command;

use Danilovl\MenuBuilderBundle\Service\MenuImporter;
use Symfony\Component\Console\Attribute\{
    Argument,
    AsCommand,
    Option
};
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use DomainException;

#[AsCommand(
    name: 'danilovl:menu-builder:import', 
    description: 'Import a menu from a JSON file produced by danilovl:menu-builder:export'
)]
readonly class MenuImportCommand
{
    public function __construct(private MenuImporter $importer) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Path to JSON file')]
        string $file,
        #[Option(description: 'Override target menu name from the file', name: 'as')]
        ?string $as = null,
    ): int {
        if (!is_file($file) || !is_readable($file)) {
            $message = sprintf('File "%s" not readable.', $file);
            $io->error($message);

            return Command::FAILURE;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            $message = sprintf('Failed to read "%s".', $file);
            $io->error($message);

            return Command::FAILURE;
        }

        $payload = json_decode($content, true);
        if (!is_array($payload) || !self::hasStringKeys($payload)) {
            $io->error('JSON payload must decode to an object/array.');

            return Command::FAILURE;
        }

        $overrideName = $as !== null && $as !== '' ? $as : null;

        /** @var array<string, mixed> $payload */
        try {
            $result = $this->importer->import($payload, $overrideName);
        } catch (DomainException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $message = sprintf('Imported %d items into menu "%s".', $result['created'], $result['menu']);
        $io->success($message);

        return Command::SUCCESS;
    }

    /**
     * @param array<int|string, mixed> $data
     */
    private static function hasStringKeys(array $data): bool
    {
        return array_all(array_keys($data), static fn (mixed $key): bool => is_string($key));

    }
}
