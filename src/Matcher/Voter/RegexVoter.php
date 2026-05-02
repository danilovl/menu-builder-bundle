<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Matcher\Voter;

use Danilovl\MenuBuilderBundle\Model\MenuItemInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\RequestStack;

class RegexVoter implements VoterInterface
{
    /** @var array<int, string> */
    private readonly array $patterns;

    /**
     * @param string|array<int, string> $patterns
     */
    public function __construct(private readonly RequestStack $requestStack, string|array $patterns = [])
    {
        $normalized = is_array($patterns) ? array_values($patterns) : [$patterns];
        foreach ($normalized as $pattern) {
            if ($pattern === '') {
                throw new InvalidArgumentException('RegexVoter pattern must be a non-empty string.');
            }

            if (@preg_match($pattern, '') === false) {
                $message = sprintf('RegexVoter pattern "%s" is not a valid regex.', $pattern);

                throw new InvalidArgumentException($message);
            }
        }
        $this->patterns = $normalized;
    }

    public function matchItem(MenuItemInterface $item): ?bool
    {
        $patterns = $this->resolvePatterns($item);
        if ($patterns === []) {
            return null;
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return null;
        }

        $uri = $request->getPathInfo();

        if (array_any($patterns, static fn ($pattern) => preg_match($pattern, $uri) === 1)) {
            return true;
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function resolvePatterns(MenuItemInterface $item): array
    {
        $perItem = $item->getAttributes()['regex_patterns'] ?? null;
        if (is_string($perItem) && $perItem !== '') {
            return [$perItem];
        }

        if (is_array($perItem)) {
            $result = array_filter(
                $perItem,
                static fn (mixed $value): bool => is_string($value) && $value !== '',
            );

            return array_values($result);
        }

        return $this->patterns;
    }
}
