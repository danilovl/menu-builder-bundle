<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Event;

use Danilovl\MenuBuilderBundle\Model\MenuItemInterface;
use Symfony\Contracts\EventDispatcher\Event;

abstract class MenuItemEvent extends Event
{
    public function __construct(public readonly MenuItemInterface $item) {}
}
