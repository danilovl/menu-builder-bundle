<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Model;

enum MenuItemType: string
{
    case LINK = 'link';
    case DIVIDER = 'divider';
    case HEADING = 'heading';
    case EXTERNAL = 'external';
    case MEGA = 'mega';

    public static function isValid(string $value): bool
    {
        return self::tryFrom($value) !== null;
    }
}
