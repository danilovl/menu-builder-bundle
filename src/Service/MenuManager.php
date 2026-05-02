<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Service;

use DateTimeImmutable;
use DomainException;
use Danilovl\MenuBuilderBundle\Builder\MenuBuilder;
use Danilovl\MenuBuilderBundle\DependencyInjection\MenuBuilderConfig;
use Danilovl\MenuBuilderBundle\Entity\MenuItem;
use Danilovl\MenuBuilderBundle\Event\{
    MenuItemDeletedEvent,
    MenuItemMovedEvent,
    MenuItemSavedEvent
};
use Danilovl\MenuBuilderBundle\Model\{
    DetachedMenuItem,
    MenuItemInterface
};
use Danilovl\MenuBuilderBundle\Exception\MenuItemNotFoundException;
use Danilovl\MenuBuilderBundle\Storage\StorageInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Exception;

readonly class MenuManager
{
    public function __construct(
        private StorageInterface $storage,
        private MenuBuilder $builder,
        private EventDispatcherInterface $dispatcher,
        private ValidatorInterface $validator,
        private MenuBuilderConfig $config,
        private RouteExistenceChecker $routeChecker
    ) {}

    /**
     * @return array<int, MenuItemInterface>
     */
    public function getTree(string $menuName): array
    {
        return $this->builder->build($menuName);
    }

    /**
     * @return array<int, MenuItemInterface>
     */
    public function getAllItems(string $menuName): array
    {
        return $this->storage->getTree($menuName, false);
    }

    public function find(int|string $id): MenuItemInterface
    {
        $item = $this->storage->findById($id);
        if ($item === null) {
            $message = sprintf('MenuItem "%s" not found.', (string) $id);

            throw new MenuItemNotFoundException($message);
        }

        return $item;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data, bool $validate = true): MenuItemInterface
    {
        $menuName = $data['menuName'] ?? null;
        $label = $data['label'] ?? null;
        if (!is_string($menuName) || $menuName === '' || !is_string($label)) {
            throw new DomainException('menuName and label are required.');
        }

        $item = $this->instantiate($menuName, $label);
        $this->hydrate($item, $data);

        $parentId = $data['parentId'] ?? null;
        if (((is_string($parentId) && $parentId !== '') || is_int($parentId))) {
            $parent = $this->storage->findById($parentId);
            if ($parent === null) {
                $message = sprintf(
                    'Parent menu item "%s" not found in menu "%s".',
                    $parentId,
                    $menuName,
                );

                throw new MenuItemNotFoundException($message);
            }
            if ($parent->getMenuName() !== $menuName) {
                $message = sprintf(
                    'Parent "%s" belongs to menu "%s", but child is being created in "%s".',
                    $parentId,
                    $parent->getMenuName(),
                    $menuName,
                );

                throw new DomainException($message);
            }

            $parent->addChild($item);
        }

        if ($validate) {
            $this->validate($item);
        }

        $this->storage->save($item);
        $event = new MenuItemSavedEvent($item, isNew: true);
        $this->dispatcher->dispatch($event);

        return $item;
    }

    public function validate(MenuItemInterface $item): void
    {
        $route = $item->getRoute();
        if ($route !== null && $route !== '' && !$this->routeChecker->exists($route)) {
            $message = sprintf('Symfony route "%s" does not exist.', $route);

            throw new DomainException($message);
        }

        $errors = $this->validator->validate($item);
        if (count($errors) === 0) {
            return;
        }

        $messages = [];
        foreach ($errors as $error) {
            $messages[] = sprintf('%s: %s', $error->getPropertyPath(), $error->getMessage());
        }
        $errorMessage = 'Invalid menu item: ' . implode('; ', $messages);

        throw new DomainException($errorMessage);
    }

    public function save(MenuItemInterface $item): void
    {
        $this->storage->save($item);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int|string $id, array $data): MenuItemInterface
    {
        $item = $this->find($id);
        $this->hydrate($item, $data);
        $this->validate($item);
        $this->storage->save($item);

        $event = new MenuItemSavedEvent($item, isNew: false);
        $this->dispatcher->dispatch($event);

        return $item;
    }

    public function delete(int|string $id): void
    {
        $item = $this->find($id);
        $this->storage->delete($item);

        $event = new MenuItemDeletedEvent($item);
        $this->dispatcher->dispatch($event);
    }

    public function duplicate(int|string $id): MenuItemInterface
    {
        $source = $this->find($id);
        $parent = $source->getParent();

        $copy = $this->cloneSubtree($source, $parent);

        $this->validate($copy);
        $this->storage->save($copy);
        $event = new MenuItemSavedEvent($copy, isNew: true);
        $this->dispatcher->dispatch($event);

        return $copy;
    }

    private function cloneSubtree(MenuItemInterface $source, ?MenuItemInterface $parent): MenuItemInterface
    {
        $copyLabel = $parent === null && $source->getParent() === null
            ? $source->getLabel() . ' (copy)'
            : $source->getLabel();

        $copy = $this->instantiate($source->getMenuName(), $copyLabel);
        $this->copyFields($source, $copy);
        $copy->setPosition($source->getPosition() + 1);

        if ($parent !== null) {
            $parent->addChild($copy);
        }

        foreach ($source->getChildren() as $child) {
            $this->cloneSubtree($child, $copy);
        }

        return $copy;
    }

    private function copyFields(MenuItemInterface $source, MenuItemInterface $target): void
    {
        $target->setUri($source->getUri());
        $target->setRoute($source->getRoute());
        $target->setRouteParams($source->getRouteParams());
        $target->setIcon($source->getIcon());
        $target->setIconImage($source->getIconImage());
        $target->setAttributes($source->getAttributes());
        $target->setActive($source->isActive());
        $target->setIsDisplayed($source->isDisplayed());
        $target->setRequiredRoles($source->getRequiredRoles());
        $target->setAllowedUsers($source->getAllowedUsers());
        $target->setTarget($source->getTarget());
        $target->setVisibility($source->getVisibility());
        $target->setTranslations($source->getTranslations());
        $target->setDependentActiveRoutes($source->getDependentActiveRoutes());
        $target->setLabelTranslated($source->isLabelTranslated());
        $target->setTranslationDomain($source->getTranslationDomain());
    }

    public function setActive(int|string $id, bool $active): MenuItemInterface
    {
        $item = $this->find($id);
        $item->setActive($active);
        $this->storage->save($item);

        $event = new MenuItemSavedEvent($item, isNew: false);
        $this->dispatcher->dispatch($event);

        return $item;
    }

    public function move(int|string $id, int|string|null $newParentId, int $newPosition): MenuItemInterface
    {
        $item = $this->find($id);
        $newParent = ($newParentId !== null && $newParentId !== '') ? $this->find($newParentId) : null;
        $this->storage->move($item, $newParent, $newPosition);

        $event = new MenuItemMovedEvent($item, $newParent, $newPosition);
        $this->dispatcher->dispatch($event);

        return $item;
    }

    /**
     * @return array<int, string>
     */
    public function getMenuNames(): array
    {
        return $this->storage->getMenuNames();
    }

    public function deleteMenu(string $name): void
    {
        $existing = $this->storage->getMenuNames();
        if (!in_array($name, $existing, true)) {
            $message = sprintf('Menu "%s" not found.', $name);

            throw new MenuItemNotFoundException($message);
        }

        $this->storage->deleteMenu($name);
    }

    public function setMenuActive(string $name, bool $active): void
    {
        $existing = $this->storage->getMenuNames();
        if (!in_array($name, $existing, true)) {
            $message = sprintf('Menu "%s" not found.', $name);

            throw new MenuItemNotFoundException($message);
        }

        $this->storage->setActiveForMenu($name, $active);
    }

    public function isMenuActive(string $name): bool
    {
        return $this->storage->isMenuActive($name);
    }

    /**
     * @return array<int, MenuItemInterface>
     */
    public function search(string $term, int $limit = 20): array
    {
        return $this->storage->search($term, $limit);
    }

    /**
     * @return array<int, MenuItemInterface>
     */
    public function getDeletedItems(string $menuName): array
    {
        return $this->storage->getDeletedItems($menuName);
    }

    public function restore(int|string $id): MenuItemInterface
    {
        $item = $this->storage->findById($id);
        if ($item !== null) {
            $this->storage->restore($item);

            return $item;
        }

        $deletedAcrossMenus = [];
        foreach ($this->storage->getMenuNames() as $menuName) {
            $deletedAcrossMenus = array_merge($deletedAcrossMenus, $this->storage->getDeletedItems($menuName));
        }

        foreach ($deletedAcrossMenus as $candidate) {
            if ((string) $candidate->getId() === (string) $id) {
                $this->storage->restore($candidate);

                return $candidate;
            }
        }

        $message = sprintf('MenuItem "%s" not found in trash.', (string) $id);

        throw new MenuItemNotFoundException($message);
    }

    public function renameMenu(string $oldName, string $newName): void
    {
        $newName = mb_trim($newName);
        if ($newName === '') {
            throw new DomainException('Menu name cannot be empty.');
        }
        if (mb_strlen($newName) > 100) {
            throw new DomainException('Menu name cannot exceed 100 characters.');
        }
        if ($oldName === $newName) {
            return;
        }

        $existing = $this->storage->getMenuNames();
        if (!in_array($oldName, $existing, true)) {
            $message = sprintf('Menu "%s" not found.', $oldName);

            throw new MenuItemNotFoundException($message);
        }
        if (in_array($newName, $existing, true)) {
            $message = sprintf('Menu "%s" already exists.', $newName);

            throw new DomainException($message);
        }

        $this->storage->renameMenu($oldName, $newName);
    }

    private function instantiate(string $menuName, string $label): MenuItemInterface
    {
        return match ($this->config->storageDriver) {
            'doctrine' => new MenuItem($menuName, $label),
            default => DetachedMenuItem::create($menuName, $label),
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    private function hydrate(MenuItemInterface $item, array $data): void
    {
        if (isset($data['label']) && is_string($data['label'])) {
            $item->setLabel($data['label']);
        }
        if (array_key_exists('uri', $data)) {
            $item->setUri(self::nullableString($data['uri']));
        }
        if (array_key_exists('route', $data)) {
            $item->setRoute(self::nullableString($data['route']));
        }
        if (isset($data['routeParams']) && is_array($data['routeParams'])) {
            $item->setRouteParams(self::stringKeyed($data['routeParams']));
        }
        if (array_key_exists('icon', $data)) {
            $item->setIcon(self::nullableString($data['icon']));
        }
        if (array_key_exists('iconImage', $data)) {
            $item->setIconImage(self::nullableString($data['iconImage']));
        }
        if (isset($data['attributes']) && is_array($data['attributes'])) {
            $item->setAttributes(self::stringKeyed($data['attributes']));
        }
        if (isset($data['position']) && (is_int($data['position']) || is_string($data['position']))) {
            $item->setPosition((int) $data['position']);
        }
        if (isset($data['active'])) {
            $item->setActive((bool) $data['active']);
        }
        if (isset($data['isDisplayed'])) {
            $item->setIsDisplayed((bool) $data['isDisplayed']);
        }
        if (isset($data['requiredRoles']) && is_array($data['requiredRoles'])) {
            $item->setRequiredRoles(self::stringList($data['requiredRoles']));
        }
        if (isset($data['allowedUsers']) && is_array($data['allowedUsers'])) {
            $item->setAllowedUsers(self::stringList($data['allowedUsers']));
        }
        if (array_key_exists('target', $data)) {
            $item->setTarget(self::nullableString($data['target']));
        }
        if (isset($data['visibility']) && is_string($data['visibility'])) {
            $item->setVisibility($data['visibility']);
        }
        if (isset($data['translations']) && is_array($data['translations'])) {
            $item->setTranslations(self::translationsArray($data['translations']));
        }
        if (isset($data['dependentActiveRoutes']) && is_array($data['dependentActiveRoutes'])) {
            $item->setDependentActiveRoutes(self::stringList($data['dependentActiveRoutes']));
        }
        if (isset($data['labelTranslated'])) {
            $item->setLabelTranslated((bool) $data['labelTranslated']);
        }
        if (array_key_exists('translationDomain', $data)) {
            $item->setTranslationDomain(self::nullableString($data['translationDomain']));
        }
        if (isset($data['type']) && is_string($data['type'])) {
            $item->setType($data['type']);
        }
        if (isset($data['column']) && (is_int($data['column']) || is_string($data['column']))) {
            $item->setColumn((int) $data['column']);
        }
        if (isset($data['cssClasses']) && is_array($data['cssClasses'])) {
            $item->setCssClasses(self::stringList($data['cssClasses']));
        }
        if (array_key_exists('publishedAt', $data)) {
            $item->setPublishedAt($this->parseDate($data['publishedAt']));
        }
        if (array_key_exists('unpublishedAt', $data)) {
            $item->setUnpublishedAt($this->parseDate($data['unpublishedAt']));
        }
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    /**
     * @param array<int|string, mixed> $data
     * @return array<string, mixed>
     */
    private static function stringKeyed(array $data): array
    {
        $clean = [];
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    /**
     * @param array<int|string, mixed> $data
     * @return array<int, string>
     */
    private static function stringList(array $data): array
    {
        $clean = [];
        foreach ($data as $value) {
            if (is_string($value)) {
                $clean[] = $value;
            }
        }

        return $clean;
    }

    /**
     * @param array<int|string, mixed> $data
     * @return array<string, array{label?: string, uri?: string}>
     */
    private static function translationsArray(array $data): array
    {
        $clean = [];
        foreach ($data as $locale => $entry) {
            if (!is_string($locale) || $locale === '' || !is_array($entry)) {
                continue;
            }
            $node = [];
            if (isset($entry['label']) && is_string($entry['label']) && $entry['label'] !== '') {
                $node['label'] = $entry['label'];
            }
            if (isset($entry['uri']) && is_string($entry['uri']) && $entry['uri'] !== '') {
                $node['uri'] = $entry['uri'];
            }
            if ($node !== []) {
                $clean[$locale] = $node;
            }
        }

        return $clean;
    }

    private function parseDate(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }
}
