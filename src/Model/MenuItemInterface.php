<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Model;

use DateTimeImmutable;

interface MenuItemInterface
{
    public function getId(): int|string|null;

    public function getLabel(): string;

    public function setLabel(string $label): self;

    public function getUri(): ?string;

    public function setUri(?string $uri): self;

    public function getRoute(): ?string;

    public function setRoute(?string $route): self;

    /**
     * @return array<string, mixed>
     */
    public function getRouteParams(): array;

    /**
     * @param array<string, mixed> $params
     */
    public function setRouteParams(array $params): self;

    public function getIcon(): ?string;

    public function setIcon(?string $icon): self;

    public function getIconImage(): ?string;

    public function setIconImage(?string $iconImage): self;

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array;

    /**
     * @param array<string, mixed> $attributes
     */
    public function setAttributes(array $attributes): self;

    public function getPosition(): int;

    public function setPosition(int $position): self;

    public function isActive(): bool;

    public function setActive(bool $active): self;

    public function isDisplayed(): bool;

    public function setIsDisplayed(bool $displayed): self;

    /**
     * @return array<int, string>
     */
    public function getRequiredRoles(): array;

    /**
     * @param array<int, string> $roles
     */
    public function setRequiredRoles(array $roles): self;

    /**
     * @return array<int, string>
     */
    public function getAllowedUsers(): array;

    /**
     * @param array<int, string> $users
     */
    public function setAllowedUsers(array $users): self;

    public function getParent(): ?self;

    public function setParent(?self $parent): self;

    /**
     * @return iterable<self>
     */
    public function getChildren(): iterable;

    public function addChild(self $child): self;

    /**
     * @param iterable<self> $children
     */
    public function addChildren(iterable $children): self;

    public function removeChild(self $child): self;

    public function hasChildren(): bool;

    public function getMenuName(): string;

    public function setMenuName(string $name): self;

    public function getTarget(): ?string;

    public function setTarget(?string $target): self;

    public function getVisibility(): string;

    public function setVisibility(string $visibility): self;

    public function getType(): string;

    public function setType(string $type): self;

    public function getColumn(): int;

    public function setColumn(int $column): self;

    /**
     * @return array<int, string>
     */
    public function getCssClasses(): array;

    /**
     * @param array<int, string> $classes
     */
    public function setCssClasses(array $classes): self;

    public function getPublishedAt(): ?DateTimeImmutable;

    public function setPublishedAt(?DateTimeImmutable $publishedAt): self;

    public function getUnpublishedAt(): ?DateTimeImmutable;

    public function setUnpublishedAt(?DateTimeImmutable $unpublishedAt): self;

    public function isPublishedAt(DateTimeImmutable $now): bool;

    /**
     * @return array<string, array{label?: string, uri?: string}>
     */
    public function getTranslations(): array;

    /**
     * @param array<string, array{label?: string, uri?: string}> $translations
     */
    public function setTranslations(array $translations): self;

    public function getLabelForLocale(string $locale): string;

    public function getUriForLocale(string $locale): ?string;

    /**
     * @return array<int, string>
     */
    public function getDependentActiveRoutes(): array;

    /**
     * @param array<int, string> $routes
     */
    public function setDependentActiveRoutes(array $routes): self;

    public function isActiveForRoute(?string $currentRoute): bool;

    public function isLabelTranslated(): bool;

    public function setLabelTranslated(bool $value): self;

    public function getTranslationDomain(): ?string;

    public function setTranslationDomain(?string $domain): self;
}
