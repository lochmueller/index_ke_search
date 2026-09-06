<?php

declare(strict_types=1);

namespace Lochmueller\IndexKeSearch\Tests\Unit\Configuration;

use Lochmueller\IndexKeSearch\Configuration\Configuration;
use Lochmueller\IndexKeSearch\Tests\Unit\AbstractTest;
use PHPUnit\Framework\Attributes\DataProvider;

class ConfigurationTest extends AbstractTest
{
    public function testDefaultsAreUsedForEmptySiteSettings(): void
    {
        $configuration = Configuration::createBySettings([]);

        self::assertTrue($configuration->enable);
        self::assertSame(0, $configuration->storagePid);
        self::assertSame(0, $configuration->targetPid);
        self::assertSame('page', $configuration->pageType);
        self::assertSame('external', $configuration->externalType);
        self::assertSame(0, $configuration->filterOption);
        self::assertTrue($configuration->pageKeywordsAsTags);
        self::assertFalse($configuration->cleanupAfterFullIndex);
    }

    public function testSiteSettingsAreCast(): void
    {
        $configuration = Configuration::createBySettings([
            'indexKeSearch.enable' => true,
            'indexKeSearch.storagePid' => '42',
            'indexKeSearch.targetPid' => 13,
            'indexKeSearch.pageType' => ' news ',
            'indexKeSearch.externalType' => 'remote',
            'indexKeSearch.filterOption' => '7',
            'indexKeSearch.pageKeywordsAsTags' => false,
            'indexKeSearch.cleanupAfterFullIndex' => true,
        ]);

        self::assertTrue($configuration->enable);
        self::assertSame(42, $configuration->storagePid);
        self::assertSame(13, $configuration->targetPid);
        self::assertSame('news', $configuration->pageType);
        self::assertSame('remote', $configuration->externalType);
        self::assertSame(7, $configuration->filterOption);
        self::assertFalse($configuration->pageKeywordsAsTags);
        self::assertTrue($configuration->cleanupAfterFullIndex);
    }

    public function testEmptyTypesFallBackToTheDefaults(): void
    {
        $configuration = Configuration::createBySettings([
            'indexKeSearch.pageType' => '   ',
            'indexKeSearch.externalType' => '',
        ]);

        self::assertSame('page', $configuration->pageType);
        self::assertSame('external', $configuration->externalType);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: bool}>
     */
    public static function validityDataProvider(): array
    {
        return [
            'complete' => [['indexKeSearch.storagePid' => 5, 'indexKeSearch.targetPid' => 6], true],
            'disabled' => [['indexKeSearch.enable' => false, 'indexKeSearch.storagePid' => 5, 'indexKeSearch.targetPid' => 6], false],
            'without storage pid' => [['indexKeSearch.targetPid' => 6], false],
            'without target pid' => [['indexKeSearch.storagePid' => 5], false],
            'empty' => [[], false],
        ];
    }

    /**
     * @param array<string, mixed> $settings
     */
    #[DataProvider('validityDataProvider')]
    public function testValidity(array $settings, bool $expected): void
    {
        self::assertSame($expected, Configuration::createBySettings($settings)->isValid());
    }
}
