<?php

declare(strict_types=1);

namespace Lochmueller\IndexKeSearch\Configuration;

/**
 * Per site configuration of the EXT:index <-> EXT:ke_search bridge.
 *
 * All values are read from the site settings, see
 * Configuration/Sets/IndexKeSearch/settings.definitions.yaml
 */
final readonly class Configuration
{
    public const DEFAULT_PAGE_TYPE = 'page';
    public const DEFAULT_EXTERNAL_TYPE = 'external';

    /**
     * Identifiers of the site settings, keyed by the property they fill.
     *
     * @var array<string, string>
     */
    public const SETTING_IDENTIFIERS = [
        'enable' => 'indexKeSearch.enable',
        'storagePid' => 'indexKeSearch.storagePid',
        'targetPid' => 'indexKeSearch.targetPid',
        'pageType' => 'indexKeSearch.pageType',
        'externalType' => 'indexKeSearch.externalType',
        'filterOption' => 'indexKeSearch.filterOption',
        'pageKeywordsAsTags' => 'indexKeSearch.pageKeywordsAsTags',
        'cleanupAfterFullIndex' => 'indexKeSearch.cleanupAfterFullIndex',
    ];

    public function __construct(
        public bool   $enable = true,
        public int    $storagePid = 0,
        public int    $targetPid = 0,
        public string $pageType = self::DEFAULT_PAGE_TYPE,
        public string $externalType = self::DEFAULT_EXTERNAL_TYPE,
        public int    $filterOption = 0,
        public bool   $pageKeywordsAsTags = true,
        public bool   $cleanupAfterFullIndex = false,
    ) {}

    /**
     * The defaults are also part of the settings definitions, but a site that does not use the site
     * set of this extension delivers no settings at all - so they are repeated here.
     *
     * @param array<string, mixed> $settings flat site settings, keyed by their setting identifier
     */
    public static function createBySettings(array $settings): self
    {
        $identifier = self::SETTING_IDENTIFIERS;

        return new self(
            enable: (bool) ($settings[$identifier['enable']] ?? true),
            storagePid: (int) ($settings[$identifier['storagePid']] ?? 0),
            targetPid: (int) ($settings[$identifier['targetPid']] ?? 0),
            pageType: trim((string) ($settings[$identifier['pageType']] ?? '')) ?: self::DEFAULT_PAGE_TYPE,
            externalType: trim((string) ($settings[$identifier['externalType']] ?? '')) ?: self::DEFAULT_EXTERNAL_TYPE,
            filterOption: (int) ($settings[$identifier['filterOption']] ?? 0),
            pageKeywordsAsTags: (bool) ($settings[$identifier['pageKeywordsAsTags']] ?? true),
            cleanupAfterFullIndex: (bool) ($settings[$identifier['cleanupAfterFullIndex']] ?? false),
        );
    }

    /**
     * The bridge can only write into ke_search if a storage folder is given. The target page is only
     * needed as fallback for documents without an own page (files and external documents), but
     * ke_search rejects every record without a target PID - so both values are mandatory.
     */
    public function isValid(): bool
    {
        return $this->enable && $this->storagePid > 0 && $this->targetPid > 0;
    }
}
