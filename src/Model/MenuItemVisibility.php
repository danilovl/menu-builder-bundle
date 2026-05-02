<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Model;

enum MenuItemVisibility: string
{
    case ALWAYS = 'always';
    case AUTHENTICATED = 'authenticated';
    case ANONYMOUS = 'anonymous';

    public static function isValid(string $value): bool
    {
        return self::tryFrom($value) !== null;
    }
}
