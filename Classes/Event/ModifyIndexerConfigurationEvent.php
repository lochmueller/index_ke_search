<?php

declare(strict_types=1);

namespace Lochmueller\IndexKeSearch\Event;

use TYPO3\CMS\Core\Site\Entity\SiteInterface;

/**
 * The bridge does not run inside a ke_search indexer configuration record, so it builds a synthetic
 * one. ke_search passes this array to its own hooks and to
 * \Tpwd\KeSearch\Event\ModifyFieldValuesBeforeStoringEvent, so third party code can recognize the
 * documents of this bridge. Use this event to adjust the synthetic configuration.
 */
final class ModifyIndexerConfigurationEvent
{
    /**
     * @param array<string, mixed> $indexerConfiguration
     */
    public function __construct(
        public array                  $indexerConfiguration,
        public readonly SiteInterface $site,
    ) {}
}
