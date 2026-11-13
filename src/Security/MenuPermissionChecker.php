<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Security;

use Danilovl\MenuBuilderBundle\Model\{
    MenuItemInterface,
    MenuItemVisibility
};
use DateTimeImmutable;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

readonly class MenuPermissionChecker
{
    public function __construct(private ?AuthorizationCheckerInterface $auth = null) {}

    public function canSee(MenuItemInterface $item): bool
    {
        if ($this->auth === null) {
            return $this->canAnonymousSee($item);
        }

        return $this->auth->isGranted(MenuItemAttribute::SEE, $item);
    }

    private function canAnonymousSee(MenuItemInterface $item): bool
    {
        $isAuthenticatedOnly = $item->getVisibility() === MenuItemVisibility::AUTHENTICATED->value;
        $hasRoleRestrictions = $item->getRequiredRoles() !== [];
        $hasUserRestrictions = $item->getAllowedUsers() !== [];

        if ($isAuthenticatedOnly || $hasRoleRestrictions || $hasUserRestrictions) {
            return false;
        }

        $now = new DateTimeImmutable;

        return $item->isPublishedAt($now);
    }
}
