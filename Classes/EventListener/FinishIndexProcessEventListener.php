<?php

declare(strict_types=1);

namespace Lochmueller\IndexKeSearch\EventListener;

use Lochmueller\Index\Domain\Repository\LogRepository;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Event\FinishIndexProcessEvent;
use Lochmueller\IndexKeSearch\Bridge\KeSearchIndexer;
use Lochmueller\IndexKeSearch\Configuration\ConfigurationLoader;
use Lochmueller\IndexKeSearch\Repository\KeSearchIndexRepository;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Closes an EXT:index process on the ke_search side: re-enables the index table keys and - if
 * enabled - removes the documents that have not been written again during a full index run.
 */
final class FinishIndexProcessEventListener implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly ConfigurationLoader     $configurationLoader,
        private readonly KeSearchIndexer         $keSearchIndexer,
        private readonly KeSearchIndexRepository $keSearchIndexRepository,
        private readonly LogRepository           $logRepository,
    ) {}

    #[AsEventListener('index-ke-search-finish-process')]
    public function __invoke(FinishIndexProcessEvent $event): void
    {
        try {
            $this->keSearchIndexer->finish();

            $configuration = $this->configurationLoader->loadBySite($event->site);
            if (!$configuration->isValid() || !$configuration->cleanupAfterFullIndex) {
                return;
            }
            if ($event->type !== IndexType::Full) {
                return;
            }

            $startTime = $this->resolveStartTime($event);
            if ($startTime === null) {
                $this->logger?->warning(
                    'Skipping ke_search cleanup: no start time for index process "' . $event->indexProcessId . '"',
                );
                return;
            }

            $deleted = $this->keSearchIndexRepository->deleteOutdated(
                storagePid: $configuration->storagePid,
                timestamp: $startTime,
                types: [$configuration->pageType, $configuration->externalType],
                typePrefixes: ['file:'],
            );

            $this->logger?->info('Removed ' . $deleted . ' outdated ke_search records', [
                'indexProcessId' => $event->indexProcessId,
                'storagePid' => $configuration->storagePid,
            ]);
        } catch (\Exception $exception) {
            $this->logger?->error($exception->getMessage(), ['exception' => $exception]);
        }
    }

    /**
     * Start and finish of an index process can be handled by different queue workers, so the start
     * time is taken from the log table of EXT:index instead of from a runtime cache.
     */
    protected function resolveStartTime(FinishIndexProcessEvent $event): ?int
    {
        $record = $this->logRepository->findByIndexProcessId($event->indexProcessId);
        $startTime = (int) ($record['start_time'] ?? 0);

        return $startTime > 0 ? $startTime : null;
    }
}
