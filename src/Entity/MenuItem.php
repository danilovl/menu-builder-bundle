<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Entity;

use DateTimeImmutable;
use InvalidArgumentException;
use Danilovl\MenuBuilderBundle\Repository\MenuItemRepository;
use Doctrine\Common\Collections\{
    ArrayCollection,
    Collection,
    Order
};
use Danilovl\MenuBuilderBundle\Model\{
    MenuItemInterface,
    MenuItemType,
    MenuItemVisibility
};
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: MenuItemRepository::class)]
#[ORM\Table(name: 'danilovl_menu_builder_item')]
#[ORM\Index(name: 'idx_menu_name', columns: ['menu_name'])]
#[ORM\Index(name: 'idx_parent_position', columns: ['parent_id', 'position'])]
#[ORM\Index(name: 'idx_deleted_at', columns: ['deleted_at'])]
#[ORM\HasLifecycleCallbacks]
class MenuItem implements MenuItemInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private int $id;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?self $parent = null;

    #[ORM\Column(name: 'menu_name', type: Types::STRING, length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $menuName;

    #[ORM\Column(name: 'label', type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $label;

    #[ORM\Column(name: 'uri', type: Types::STRING, length: 1_024, nullable: true)]
    #[Assert\Length(max: 1_024)]
    private ?string $uri = null;

    #[ORM\Column(name: 'route', type: Types::STRING, length: 255, nullable: true)]
    private ?string $route = null;

    /** @var array<string, mixed> */
    #[ORM\Column(name: 'route_params', type: Types::JSON)]
    private array $routeParams = [];

    #[ORM\Column(name: 'icon', type: Types::STRING, length: 100, nullable: true)]
    private ?string $icon = null;

    #[ORM\Column(name: 'icon_image', type: Types::STRING, length: 1_024, nullable: true)]
    #[Assert\Length(max: 1_024)]
    private ?string $iconImage = null;

    /** @var array<string, mixed> */
    #[ORM\Column(name: 'attributes', type: Types::JSON)]
    private array $attributes = [];

    #[ORM\Column(name: 'position', type: Types::INTEGER)]
    private int $position = 0;

    #[ORM\Column(name: 'active', type: Types::BOOLEAN)]
    private bool $active = true;

    #[ORM\Column(name: 'is_displayed', type: Types::BOOLEAN)]
    private bool $isDisplayed = true;

    /** @var array<int, string> */
    #[ORM\Column(name: 'required_roles', type: Types::JSON)]
    private array $requiredRoles = [];

    /** @var array<int, string> */
    #[ORM\Column(name: 'allowed_users', type: Types::JSON)]
    private array $allowedUsers = [];

    #[ORM\Column(name: 'target', type: Types::STRING, length: 16, nullable: true)]
    private ?string $target = null;

    #[ORM\Column(name: 'visibility', type: Types::STRING, length: 32, enumType: MenuItemVisibility::class, options: ['default' => MenuItemVisibility::ALWAYS->value])]
    private MenuItemVisibility $visibility = MenuItemVisibility::ALWAYS;

    #[ORM\Column(name: 'type', type: Types::STRING, length: 16, enumType: MenuItemType::class, options: ['default' => MenuItemType::LINK->value])]
    private MenuItemType $type = MenuItemType::LINK;

    #[ORM\Column(name: '`column`', type: Types::INTEGER, options: ['default' => 0])]
    private int $column = 0;

    /** @var array<int, string> */
    #[ORM\Column(name: 'css_classes', type: Types::JSON)]
    private array $cssClasses = [];

    #[ORM\Column(name: 'published_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $publishedAt = null;

    #[ORM\Column(name: 'unpublished_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $unpublishedAt = null;

    /** @var array<string, array{label?: string, uri?: string}> */
    #[ORM\Column(name: 'translations', type: Types::JSON)]
    private array $translations = [];

    /** @var array<int, string> */
    #[ORM\Column(name: 'dependent_active_routes', type: Types::JSON)]
    private array $dependentActiveRoutes = [];

    #[ORM\Column(name: 'label_translated', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $labelTranslated = false;

    #[ORM\Column(name: 'translation_domain', type: Types::STRING, length: 64, nullable: true)]
    private ?string $translationDomain = null;

    /** @var Collection<int, self> */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['position' => Order::Ascending->value])]
    private Collection $children;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    #[ORM\Column(name: 'deleted_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $deletedAt = null;

    public function __construct(string $menuName, string $label)
    {
        $this->menuName = $menuName;
        $this->label = $label;
        $this->children = new ArrayCollection;
    }

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getMenuName(): string
    {
        return $this->menuName;
    }

    public function setMenuName(string $name): self
    {
        $this->menuName = $name;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getUri(): ?string
    {
        return $this->uri;
    }

    public function setUri(?string $uri): self
    {
        $this->uri = $uri;

        return $this;
    }

    public function getRoute(): ?string
    {
        return $this->route;
    }

    public function setRoute(?string $route): self
    {
        $this->route = $route;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRouteParams(): array
    {
        return $this->routeParams;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function setRouteParams(array $params): self
    {
        $this->routeParams = $params;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function getIconImage(): ?string
    {
        return $this->iconImage;
    }

    public function setIconImage(?string $iconImage): self
    {
        $this->iconImage = $iconImage === '' ? null : $iconImage;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function setAttributes(array $attributes): self
    {
        $this->attributes = $attributes;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function isDisplayed(): bool
    {
        return $this->isDisplayed;
    }

    public function setIsDisplayed(bool $displayed): self
    {
        $this->isDisplayed = $displayed;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getRequiredRoles(): array
    {
        return $this->requiredRoles;
    }

    /**
     * @param array<int, string> $roles
     */
    public function setRequiredRoles(array $roles): self
    {
        $unique = array_unique($roles);
        $this->requiredRoles = array_values($unique);

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getAllowedUsers(): array
    {
        return $this->allowedUsers;
    }

    /**
     * @param array<int, string> $users
     */
    public function setAllowedUsers(array $users): self
    {
        $unique = array_unique($users);
        $this->allowedUsers = array_values($unique);

        return $this;
    }

    public function getTarget(): ?string
    {
        return $this->target;
    }

    public function setTarget(?string $target): self
    {
        $this->target = $target === null || $target === '' ? null : $target;

        return $this;
    }

    public function getVisibility(): string
    {
        return $this->visibility->value;
    }

    public function getVisibilityEnum(): MenuItemVisibility
    {
        return $this->visibility;
    }

    public function setVisibility(string|MenuItemVisibility $visibility): self
    {
        if ($visibility instanceof MenuItemVisibility) {
            $this->visibility = $visibility;

            return $this;
        }
        $resolved = MenuItemVisibility::tryFrom($visibility);
        if ($resolved === null) {
            throw new InvalidArgumentException('Invalid visibility: ' . $visibility);
        }
        $this->visibility = $resolved;

        return $this;
    }

    public function getType(): string
    {
        return $this->type->value;
    }

    public function setType(string $type): self
    {
        $resolved = MenuItemType::tryFrom($type);
        if ($resolved === null) {
            throw new InvalidArgumentException('Invalid type: ' . $type);
        }

        $this->type = $resolved;

        return $this;
    }

    public function getColumn(): int
    {
        return $this->column;
    }

    public function setColumn(int $column): self
    {
        $this->column = max(0, $column);

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getCssClasses(): array
    {
        return $this->cssClasses;
    }

    /**
     * @param array<int, string> $classes
     */
    public function setCssClasses(array $classes): self
    {
        $clean = [];
        foreach ($classes as $value) {
            if ($value === '') {
                continue;
            }
            $trimmed = mb_trim($value);
            if ($trimmed === '' || in_array($trimmed, $clean, true)) {
                continue;
            }

            $clean[] = $trimmed;
        }

        $this->cssClasses = $clean;

        return $this;
    }

    public function getPublishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?DateTimeImmutable $publishedAt): self
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getUnpublishedAt(): ?DateTimeImmutable
    {
        return $this->unpublishedAt;
    }

    public function setUnpublishedAt(?DateTimeImmutable $unpublishedAt): self
    {
        $this->unpublishedAt = $unpublishedAt;

        return $this;
    }

    public function isPublishedAt(DateTimeImmutable $now): bool
    {
        if ($this->publishedAt !== null && $now < $this->publishedAt) {
            return false;
        }

        return !($this->unpublishedAt !== null && $now >= $this->unpublishedAt);
    }

    /**
     * @return array<string, array{label?: string, uri?: string}>
     */
    public function getTranslations(): array
    {
        return $this->translations;
    }

    /**
     * @param array<string, array{label?: string, uri?: string}> $translations
     */
    public function setTranslations(array $translations): self
    {
        $clean = [];
        foreach ($translations as $locale => $data) {
            if ($locale === '') {
                continue;
            }
            $entry = [];
            if (isset($data['label']) && $data['label'] !== '') {
                $entry['label'] = $data['label'];
            }
            if (isset($data['uri']) && $data['uri'] !== '') {
                $entry['uri'] = $data['uri'];
            }
            if ($entry !== []) {
                $clean[$locale] = $entry;
            }
        }
        $this->translations = $clean;

        return $this;
    }

    public function getLabelForLocale(string $locale): string
    {
        return $this->translations[$locale]['label'] ?? $this->label;
    }

    public function getUriForLocale(string $locale): ?string
    {
        return $this->translations[$locale]['uri'] ?? $this->uri;
    }

    /**
     * @return array<int, string>
     */
    public function getDependentActiveRoutes(): array
    {
        return $this->dependentActiveRoutes;
    }

    /**
     * @param array<int, string> $routes
     */
    public function setDependentActiveRoutes(array $routes): self
    {
        $clean = [];
        foreach ($routes as $r) {
            if ($r !== '' && !in_array($r, $clean, true)) {
                $clean[] = $r;
            }
        }
        $this->dependentActiveRoutes = $clean;

        return $this;
    }

    public function isActiveForRoute(?string $currentRoute): bool
    {
        if ($currentRoute === null || $currentRoute === '') {
            return false;
        }
        if ($this->route === $currentRoute) {
            return true;
        }

        return in_array($currentRoute, $this->dependentActiveRoutes, true);
    }

    public function isLabelTranslated(): bool
    {
        return $this->labelTranslated;
    }

    public function setLabelTranslated(bool $value): self
    {
        $this->labelTranslated = $value;

        return $this;
    }

    public function getTranslationDomain(): ?string
    {
        return $this->translationDomain;
    }

    public function setTranslationDomain(?string $domain): self
    {
        $this->translationDomain = empty($domain) ? null : $domain;

        return $this;
    }

    public function getParent(): ?MenuItemInterface
    {
        return $this->parent;
    }

    public function setParent(?MenuItemInterface $parent): self
    {
        if ($parent !== null && !$parent instanceof self) {
            throw new InvalidArgumentException('Parent must be an instance of ' . self::class);
        }
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(MenuItemInterface $child): self
    {
        if (!$child instanceof self) {
            throw new InvalidArgumentException('Child must be an instance of ' . self::class);
        }
        if ($this->children->contains($child)) {
            return $this;
        }

        $this->children->add($child);
        $child->setParent($this);

        return $this;
    }

    /**
     * @param iterable<self> $children
     */
    public function addChildren(iterable $children): self
    {
        foreach ($children as $child) {
            $this->addChild($child);
        }

        return $this;
    }

    public function removeChild(MenuItemInterface $child): self
    {
        if (!$child instanceof self) {
            return $this;
        }
        if (!$this->children->removeElement($child)) {
            return $this;
        }
        if ($child->getParent() === $this) {
            $child->setParent(null);
        }

        return $this;
    }

    public function hasChildren(): bool
    {
        return !$this->children->isEmpty();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?DateTimeImmutable $deletedAt): self
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new DateTimeImmutable;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new DateTimeImmutable;
    }

    #[Assert\Callback]
    public function validateLink(ExecutionContextInterface $context): void
    {
        if ($this->type === MenuItemType::DIVIDER || $this->type === MenuItemType::HEADING) {
            return;
        }

        if ($this->hasChildren()) {
            return;
        }

        if (empty($this->uri) && empty($this->route)) {
            $context->buildViolation('MenuItem must have either uri or route set.')
                ->atPath('uri')
                ->addViolation();
        }
    }
}
