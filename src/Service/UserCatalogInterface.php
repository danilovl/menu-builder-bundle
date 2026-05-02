<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Service;

interface UserCatalogInterface
{
    /**
     * @return array<int, string>
     */
    public function search(string $term, int $limit): array;
}
