<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Tests\Unit\Service;

use Danilovl\MenuBuilderBundle\Service\NullUserCatalog;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NullUserCatalogTest extends TestCase
{
    #[DataProvider('provideSearchAlwaysReturnsEmptyArrayCases')]
    public function testSearchAlwaysReturnsEmptyArray(string $term, int $limit): void
    {
        $catalog = new NullUserCatalog;

        $result = $catalog->search($term, $limit);

        $this->assertSame([], $result);
    }

    public static function provideSearchAlwaysReturnsEmptyArrayCases(): Generator
    {
        yield 'empty term' => ['', 10];
        yield 'normal term' => ['john', 20];
        yield 'zero limit' => ['anything', 0];
        yield 'unicode term' => ['Ivan', 5];
    }
}
