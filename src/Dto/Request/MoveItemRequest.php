<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Dto\Request;

use Symfony\Component\Validator\Constraints as Assert;

readonly class MoveItemRequest
{
    public function __construct(
        public string|int|null $parentId = null,
        #[Assert\PositiveOrZero]
        public int $position = 0
    ) {}
}
