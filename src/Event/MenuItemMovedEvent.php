<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Event;

use Danilovl\MenuBuilderBundle\Model\MenuItemInterface;

class MenuItemMovedEvent extends MenuItemEvent
{
    public function __construct(
        MenuItemInterface $item,
        public readonly ?MenuItemInterface $newParent,
        public readonly int $newPosition
    ) {
        parent::__construct($item);
    }
}
