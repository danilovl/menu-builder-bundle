<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Security;

final class UriSafety
{
    public const array SAFE_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    private const string SCHEME_PATTERN = '~^([a-z][a-z0-9+.\-]*):~i';

    public static function isSafe(string $uri): bool
    {
        $scheme = self::extractScheme($uri);
        if ($scheme === null) {
            return true;
        }

        return in_array($scheme, self::SAFE_SCHEMES, true);
    }

    public static function extractScheme(string $uri): ?string
    {
        $normalizedUri = preg_replace('~[\x00-\x20\x7F]+~', '', $uri) ?? '';

        if (preg_match(self::SCHEME_PATTERN, $normalizedUri, $matches) !== 1) {
            return null;
        }

        return mb_strtolower($matches[1]);
    }
}
