<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Event;

use Danilovl\MenuBuilderBundle\Model\MenuItemInterface;

class MenuItemSavedEvent extends MenuItemEvent
{
    public function __construct(MenuItemInterface $item, public readonly bool $isNew)
    {
        parent::__construct($item);
    }
}
