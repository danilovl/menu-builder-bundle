<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Security;

use Danilovl\MenuBuilderBundle\Model\MenuItemInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

readonly class MenuPermissionChecker
{
    public function __construct(private AuthorizationCheckerInterface $auth) {}

    public function canSee(MenuItemInterface $item): bool
    {
        return $this->auth->isGranted(MenuItemAttribute::SEE, $item);
    }
}
