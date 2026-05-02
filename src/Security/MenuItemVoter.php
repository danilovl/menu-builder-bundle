<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Security;

use DateTimeImmutable;
use Danilovl\MenuBuilderBundle\Model\{
    MenuItemInterface,
    MenuItemVisibility
};
use Symfony\Component\Security\Core\Authorization\Voter\{
    Vote,
    Voter
};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @extends Voter<string, MenuItemInterface>
 */
class MenuItemVoter extends Voter
{
    public function __construct(
        private readonly Security $security,
        private readonly AuthorizationCheckerInterface $auth
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === MenuItemAttribute::SEE && $subject instanceof MenuItemInterface;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $isAuthenticated = $this->security->getUser() !== null;
        $visibility = $subject->getVisibility();

        if ($visibility === MenuItemVisibility::AUTHENTICATED->value && !$isAuthenticated) {
            return false;
        }
        if ($visibility === MenuItemVisibility::ANONYMOUS->value && $isAuthenticated) {
            return false;
        }

        $required = $subject->getRequiredRoles();
        if ($required !== [] && !$this->hasAnyRole($required)) {
            return false;
        }

        $allowed = $subject->getAllowedUsers();
        if ($allowed !== []) {
            $user = $this->security->getUser();
            if ($user === null) {
                return false;
            }
            if (!in_array($user->getUserIdentifier(), $allowed, true)) {
                return false;
            }
        }

        $now = new DateTimeImmutable;

        return !(!$subject->isPublishedAt($now));
    }

    /**
     * @param array<int, string> $roles
     */
    private function hasAnyRole(array $roles): bool
    {
        return array_any($roles, fn (string $role): bool => $this->auth->isGranted($role));

    }
}
