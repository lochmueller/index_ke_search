<?php

declare(strict_types=1);

namespace Lochmueller\IndexKeSearch\Tests\Unit\Utility;

use Lochmueller\IndexKeSearch\Tests\Unit\AbstractTest;
use Lochmueller\IndexKeSearch\Utility\UriHash;

class UriHashTest extends AbstractTest
{
    public function testHashIsDeterministicAndFitsIntoTheHashColumn(): void
    {
        $uri = 'https://www.example.com/products/detail';

        self::assertSame(UriHash::hash($uri), UriHash::hash($uri));
        self::assertSame(32, strlen(UriHash::hash($uri)));
        self::assertNotSame(UriHash::hash($uri), UriHash::hash($uri . '/'));
    }

    public function testPseudoUidIsAPositiveIntegerAndDeterministic(): void
    {
        $uri = 'https://www.example.com/imprint';

        self::assertSame(UriHash::pseudoUid($uri), UriHash::pseudoUid($uri));
        self::assertGreaterThan(0, UriHash::pseudoUid($uri));
    }
}
