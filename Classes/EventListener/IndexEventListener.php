<?php

declare(strict_types=1);

namespace Lochmueller\IndexKeSearch\EventListener;

use Lochmueller\Index\Event\IndexFileEvent;
use Lochmueller\Index\Event\IndexPageEvent;
use Lochmueller\IndexKeSearch\Bridge\KeSearchIndexer;
use Lochmueller\IndexKeSearch\Configuration\ConfigurationLoader;
use Lochmueller\IndexKeSearch\Event\BeforeStoreDocumentEvent;
use Lochmueller\IndexKeSearch\Mapper\DocumentMapper;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Writes every page and file that EXT:index has indexed into the ke_search index.
 */
final class IndexEventListener implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly ConfigurationLoader      $configurationLoader,
        private readonly DocumentMapper           $documentMapper,
        private readonly KeSearchIndexer          $keSearchIndexer,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    #[AsEventListener('index-ke-search-index')]
    public function __invoke(IndexPageEvent|IndexFileEvent $event): void
    {
        try {
            $configuration = $this->configurationLoader->loadBySite($event->site);
            if (!$configuration->isValid()) {
                $this->logger?->debug('Skipping site "' . $event->site->getIdentifier() . '": no valid ke_search bridge configuration');
                return;
            }

            $document = $this->documentMapper->map($event, $configuration);
            if ($document === null) {
                return;
            }

            $beforeStoreEvent = new BeforeStoreDocumentEvent($document, $event->site, $event);
            $this->eventDispatcher->dispatch($beforeStoreEvent);
            if (!$beforeStoreEvent->store) {
                return;
            }

            $this->keSearchIndexer->store($beforeStoreEvent->document, $event->site, $configuration);
        } catch (\Exception $exception) {
            $this->logger?->error($exception->getMessage(), ['exception' => $exception]);
        }
    }
}
