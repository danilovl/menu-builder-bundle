<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Tests\Unit\Security;

use Danilovl\MenuBuilderBundle\Security\UriSafety;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UriSafetyTest extends TestCase
{
    #[DataProvider('provideSafeUriCases')]
    public function testSafeUri(string $uri): void
    {
        $isSafe = UriSafety::isSafe($uri);

        $this->assertTrue($isSafe);
    }

    #[DataProvider('provideUnsafeUriCases')]
    public function testUnsafeUri(string $uri): void
    {
        $isSafe = UriSafety::isSafe($uri);

        $this->assertFalse($isSafe);
    }

    #[DataProvider('provideExtractSchemeCases')]
    public function testExtractScheme(string $uri, ?string $expected): void
    {
        $scheme = UriSafety::extractScheme($uri);

        $this->assertSame($expected, $scheme);
    }

    public static function provideSafeUriCases(): Generator
    {
        yield 'absolute path' => ['/path'];
        yield 'relative path' => ['path/to'];
        yield 'protocol relative' => ['//example.com/x'];
        yield 'anchor' => ['#anchor'];
        yield 'query' => ['?q=1'];
        yield 'http' => ['http://example.com'];
        yield 'https upper case' => ['HTTPS://example.com'];
        yield 'mailto' => ['mailto:a@example.com'];
        yield 'tel' => ['tel:+420123456789'];
        yield 'colon in path' => ['/page?time=10:30'];
        yield 'colon after slash' => ['./javascript:alert(1)'];
    }

    public static function provideUnsafeUriCases(): Generator
    {
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'javascript mixed case and spaces' => [' JaVaScRiPt:alert(1)'];
        yield 'javascript with tab' => ["java\tscript:alert(1)"];
        yield 'javascript with newline' => ["java\nscript:alert(1)"];
        yield 'javascript with null byte' => ["java\0script:alert(1)"];
        yield 'javascript port-like' => ['javascript:80'];
        yield 'data' => ['data:text/html;base64,PHNjcmlwdD4='];
        yield 'vbscript' => ['vbscript:msgbox(1)'];
        yield 'not whitelisted ftp' => ['ftp://example.com'];
        yield 'unknown scheme' => ['foo:bar'];
    }

    public static function provideExtractSchemeCases(): Generator
    {
        yield 'javascript with control chars' => [" Java\tScript:alert(1)", 'javascript'];
        yield 'https' => ['https://example.com', 'https'];
        yield 'path with colons' => ['/path:with:colons', null];
        yield 'protocol relative' => ['//example.com', null];
    }
}
