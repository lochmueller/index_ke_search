<?php

declare(strict_types=1);

namespace Lochmueller\IndexKeSearch\Event;

use TYPO3\CMS\Core\Site\Entity\SiteInterface;

/**
 * Dispatched right before the ke_search records of a de-indexed URI are removed.
 */
final class BeforeDeleteDocumentEvent
{
    public function __construct(
        public readonly string        $uri,
        public string                 $uriHash,
        public int                    $storagePid,
        public readonly SiteInterface $site,
        public bool                   $delete = true,
    ) {}
}
