<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Dto\Request;

readonly class ToggleActiveRequest
{
    public function __construct(public bool $active = true) {}
}
