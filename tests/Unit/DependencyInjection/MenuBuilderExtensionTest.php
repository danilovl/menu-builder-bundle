<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Tests\Unit\DependencyInjection;

use Danilovl\MenuBuilderBundle\DependencyInjection\MenuBuilderExtension;
use Danilovl\MenuBuilderBundle\EventListener\SameOriginRequestListener;
use Danilovl\MenuBuilderBundle\Security\MenuItemVoter;
use Danilovl\MenuBuilderBundle\Validator\Constraints\{
    SafeUri,
    SafeUriValidator
};
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class MenuBuilderExtensionTest extends TestCase
{
    private const string EXCLUDED_TAG = 'container.excluded';

    /**
     * @param array<string, class-string>|null $bundles
     */
    private function load(?array $bundles): ContainerBuilder
    {
        $container = new ContainerBuilder;
        if ($bundles !== null) {
            $container->setParameter('kernel.bundles', $bundles);
        }

        $extension = new MenuBuilderExtension;
        $extension->load([], $container);

        return $container;
    }

    public function testVoterIsRegisteredWhenSecurityBundleIsEnabled(): void
    {
        $container = $this->load(['SecurityBundle' => SecurityBundle::class]);

        $hasVoter = $container->hasDefinition(MenuItemVoter::class);

        $this->assertTrue($hasVoter);
    }

    public function testVoterIsRemovedWhenSecurityBundleIsNotEnabled(): void
    {
        $container = $this->load([]);

        $hasVoter = $container->hasDefinition(MenuItemVoter::class);

        $this->assertFalse($hasVoter);
    }

    public function testVoterIsRemovedWithoutKernelBundlesParameter(): void
    {
        $container = $this->load(null);

        $hasVoter = $container->hasDefinition(MenuItemVoter::class);

        $this->assertFalse($hasVoter);
    }

    public function testListenerAndValidatorAreRegistered(): void
    {
        $container = $this->load([]);

        $listenerDefinition = $container->getDefinition(SameOriginRequestListener::class);
        $isListenerExcluded = $listenerDefinition->hasTag(self::EXCLUDED_TAG);

        $validatorDefinition = $container->getDefinition(SafeUriValidator::class);
        $isValidatorExcluded = $validatorDefinition->hasTag(self::EXCLUDED_TAG);

        $constraintDefinition = $container->getDefinition(SafeUri::class);
        $isConstraintExcluded = $constraintDefinition->hasTag(self::EXCLUDED_TAG);

        $this->assertFalse($isListenerExcluded);
        $this->assertFalse($isValidatorExcluded);
        $this->assertTrue($isConstraintExcluded);
    }
}
