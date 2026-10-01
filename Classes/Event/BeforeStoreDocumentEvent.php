<?php

declare(strict_types=1);

namespace Lochmueller\IndexKeSearch\Event;

use Lochmueller\Index\Event\IndexFileEvent;
use Lochmueller\Index\Event\IndexPageEvent;
use Lochmueller\IndexKeSearch\Dto\IndexDocument;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

/**
 * Dispatched right before a document is handed over to ke_search. Modify the document or set
 * $store to false to skip it.
 */
final class BeforeStoreDocumentEvent
{
    public function __construct(
        public IndexDocument                  $document,
        public readonly SiteInterface         $site,
        public readonly IndexPageEvent|IndexFileEvent $sourceEvent,
        public bool                           $store = true,
    ) {}
}
