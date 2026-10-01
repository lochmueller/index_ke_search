<?php

declare(strict_types=1);

namespace Lochmueller\IndexKeSearch\EventListener;

use Lochmueller\Index\Event\DeIndexDocumentEvent;
use Lochmueller\IndexKeSearch\Configuration\ConfigurationLoader;
use Lochmueller\IndexKeSearch\Event\BeforeDeleteDocumentEvent;
use Lochmueller\IndexKeSearch\Repository\KeSearchIndexRepository;
use Lochmueller\IndexKeSearch\Utility\UriHash;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Removes the ke_search records of a document that EXT:index has de-indexed (e.g. a deleted or
 * hidden page).
 */
final class DeIndexDocumentEventListener implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly ConfigurationLoader      $configurationLoader,
        private readonly KeSearchIndexRepository  $keSearchIndexRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    #[AsEventListener('index-ke-search-deindex')]
    public function __invoke(DeIndexDocumentEvent $event): void
    {
        try {
            $configuration = $this->configurationLoader->loadBySite($event->site);
            if (!$configuration->isValid()) {
                return;
            }

            $beforeDeleteEvent = new BeforeDeleteDocumentEvent(
                uri: $event->uri,
                uriHash: UriHash::hash($event->uri),
                storagePid: $configuration->storagePid,
                site: $event->site,
            );
            $this->eventDispatcher->dispatch($beforeDeleteEvent);
            if (!$beforeDeleteEvent->delete) {
                return;
            }

            $deleted = $this->keSearchIndexRepository->deleteByUriHash(
                $beforeDeleteEvent->uriHash,
                $beforeDeleteEvent->storagePid,
                [$configuration->pageType, $configuration->externalType],
            );

            $this->logger?->debug('Removed ' . $deleted . ' ke_search records for "' . $event->uri . '"');
        } catch (\Exception $exception) {
            $this->logger?->error($exception->getMessage(), ['exception' => $exception]);
        }
    }
}
