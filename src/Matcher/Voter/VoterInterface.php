<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Matcher\Voter;

use Danilovl\MenuBuilderBundle\Model\MenuItemInterface;

interface VoterInterface
{
    public function matchItem(MenuItemInterface $item): ?bool;
}
