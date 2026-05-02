<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Matcher;

use Danilovl\MenuBuilderBundle\Matcher\Voter\VoterInterface;
use Danilovl\MenuBuilderBundle\Model\MenuItemInterface;
use SplObjectStorage;

class Matcher
{
    /** @var SplObjectStorage<MenuItemInterface, bool> */
    private readonly SplObjectStorage $cache;

    /**
     * @param iterable<VoterInterface> $voters
     */
    public function __construct(private readonly iterable $voters = [])
    {
        $this->cache = new SplObjectStorage;
    }

    public function isCurrent(MenuItemInterface $item): bool
    {
        if ($this->cache->offsetExists($item)) {
            return $this->cache[$item];
        }

        $result = false;
        foreach ($this->voters as $voter) {
            $vote = $voter->matchItem($item);
            if ($vote === null) {
                continue;
            }
            $result = $vote;

            break;
        }

        $this->cache[$item] = $result;

        return $result;
    }

    public function isAncestor(MenuItemInterface $item, ?int $depth = null): bool
    {
        if ($depth === 0) {
            return false;
        }

        $childDepth = $depth === null ? null : $depth - 1;
        foreach ($item->getChildren() as $child) {
            if ($this->isCurrent($child) || $this->isAncestor($child, $childDepth)) {
                return true;
            }
        }

        return false;
    }

    public function clear(): void
    {
        $this->cache->removeAll($this->cache);
    }
}
