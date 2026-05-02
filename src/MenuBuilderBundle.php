<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle;

use Danilovl\MenuBuilderBundle\DependencyInjection\MenuBuilderExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class MenuBuilderBundle extends Bundle
{
    public function getContainerExtension(): MenuBuilderExtension
    {
        return new MenuBuilderExtension;
    }
}
