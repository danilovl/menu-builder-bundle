<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Dto\Request;

use Symfony\Component\Validator\Constraints as Assert;

readonly class BulkItemsRequest
{
    /**
     * @param array<int, string|int> $ids
     */
    public function __construct(
        #[Assert\Count(min: 1)]
        public array $ids,
        public ?bool $active = null
    ) {}
}
