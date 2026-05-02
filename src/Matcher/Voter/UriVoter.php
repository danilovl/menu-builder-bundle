<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Matcher\Voter;

use Danilovl\MenuBuilderBundle\Model\MenuItemInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class UriVoter implements VoterInterface
{
    public function __construct(private readonly RequestStack $requestStack) {}

    public function matchItem(MenuItemInterface $item): ?bool
    {
        $itemUri = $item->getUri();
        if ($itemUri === null || $itemUri === '') {
            return null;
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return null;
        }

        $currentUri = $request->getPathInfo();

        return $itemUri === $currentUri || mb_rtrim($itemUri, '/') === mb_rtrim($currentUri, '/');
    }
}
