<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Service;

use DomainException;
use Danilovl\MenuBuilderBundle\Model\MenuItemInterface;
use Danilovl\MenuBuilderBundle\Security\UriSafety;

readonly class MenuImporter
{
    public function __construct(private MenuManager $menus) {}

    /**
     * @param array<string, mixed> $payload
     * @return array{menu: string, created: int}
     */
    public function import(array $payload, ?string $overrideMenuName = null): array
    {
        $menuName = $overrideMenuName ?? ($payload['menu'] ?? null);
        if (!is_string($menuName) || $menuName === '') {
            throw new DomainException('Import payload must contain a menu name.');
        }

        $items = $payload['items'] ?? null;
        if (!is_array($items)) {
            throw new DomainException('Import payload must contain "items" array.');
        }

        $created = 0;
        foreach ($items as $itemData) {
            if (!is_array($itemData) || !self::isStringKeyed($itemData)) {
                continue;
            }

            /** @var array<string, mixed> $itemData */
            $created += $this->importItem($menuName, $itemData, null);
        }

        return ['menu' => $menuName, 'created' => $created];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function importItem(string $menuName, array $data, ?MenuItemInterface $parent): int
    {
        $payload = $data;
        $payload['menuName'] = $menuName;
        unset($payload['id']);
        unset($payload['children']);

        if ($parent !== null) {
            $payload['parentId'] = $parent->getId();
        }

        if (!isset($payload['label']) || !is_string($payload['label'])) {
            throw new DomainException('Each item must have a string "label".');
        }

        self::assertSafeUris($payload);

        $item = $this->menus->create($payload, false);
        $count = 1;

        $children = $data['children'] ?? [];
        if (is_array($children)) {
            foreach ($children as $childData) {
                if (!is_array($childData) || !self::isStringKeyed($childData)) {
                    continue;
                }
                /** @var array<string, mixed> $childData */
                $count += $this->importItem($menuName, $childData, $item);
            }
        }

        $this->menus->validate($item);
        $this->menus->save($item);

        return $count;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function assertSafeUris(array $data): void
    {
        $uris = ['uri' => $data['uri'] ?? null];

        $translations = $data['translations'] ?? [];
        if (is_array($translations)) {
            foreach ($translations as $locale => $translation) {
                if (is_array($translation)) {
                    $uris[sprintf('translations[%s].uri', $locale)] = $translation['uri'] ?? null;
                }
            }
        }

        foreach ($uris as $path => $uri) {
            if (!is_string($uri) || $uri === '' || UriSafety::isSafe($uri)) {
                continue;
            }

            $message = sprintf(
                'Imported item "%s": %s: URI scheme "%s" is not allowed.',
                is_string($data['label'] ?? null) ? $data['label'] : '',
                $path,
                (string) UriSafety::extractScheme($uri)
            );

            throw new DomainException($message);
        }
    }

    /**
     * @param array<int|string, mixed> $data
     */
    private static function isStringKeyed(array $data): bool
    {
        return array_all(array_keys($data), static fn (mixed $key): bool => is_string($key));

    }
}
