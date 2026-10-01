<?php

declare(strict_types=1);

namespace Lochmueller\IndexKeSearch\Bridge;

use Lochmueller\IndexKeSearch\Configuration\Configuration;
use Lochmueller\IndexKeSearch\Dto\IndexDocument;
use Lochmueller\IndexKeSearch\Event\ModifyIndexerConfigurationEvent;
use Lochmueller\IndexKeSearch\Repository\KeSearchIndexRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Tpwd\KeSearch\Indexer\IndexerRunner;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Thin wrapper around \Tpwd\KeSearch\Indexer\IndexerRunner.
 *
 * The runner is normally driven by ke_search itself (via startIndexing()), which does three things
 * before the first record is stored:
 *
 * 1. collect the additional index columns registered by the "registerAdditionalFields" hook
 * 2. create the MySQL prepared statements used by insertRecordIntoIndex()/updateRecordInIndex()
 * 3. set the indexer configuration of the current loop
 *
 * The bridge is triggered by EXT:index instead, so it has to take care of that setup itself. The
 * setup is done lazily and only once per PHP process, because the prepared statements live in the
 * database session.
 */
class KeSearchIndexer implements SingletonInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Value of the synthetic "type" of the indexer configuration. It is never a real ke_search
     * indexer type, which keeps the bridge out of the ke_search default indexer handling.
     */
    public const INDEXER_TYPE = 'index';

    private bool $prepared = false;

    public function __construct(
        private readonly IndexerRunner            $indexerRunner,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ConnectionPool           $connectionPool,
    ) {}

    public function store(IndexDocument $document, SiteInterface $site, Configuration $configuration): bool
    {
        $this->prepare();
        $this->indexerRunner->indexerConfig = $this->buildIndexerConfiguration($site, $configuration);
        $this->indexerRunner->indexingErrors = [];

        $stored = $this->indexerRunner->storeInIndex(
            $document->storagePid,
            $document->title,
            $document->type,
            $document->targetPid,
            $document->content,
            $document->tags,
            $document->params,
            $document->abstract,
            $document->language,
            $document->starttime,
            $document->endtime,
            $document->feGroup,
            false,
            $document->additionalFields,
        );

        if (!$stored) {
            $this->logger?->error('ke_search refused the document "' . $document->title . '"', [
                'type' => $document->type,
                'storagePid' => $document->storagePid,
                'targetPid' => $document->targetPid,
                'errors' => $this->indexerRunner->indexingErrors,
            ]);
        }

        return $stored;
    }

    /**
     * Counterpart of prepare(): re-enables the table keys that prepareStatements() may have disabled
     * and releases the prepared statements of the database session.
     *
     * \Tpwd\KeSearch\Indexer\IndexerRunner::cleanUpProcessAfterIndexing() does the same, but it
     * additionally clears the ke_search indexer status registry - which would drop the lock of a
     * native ke_search indexer run that happens to run at the same time.
     */
    public function finish(): void
    {
        if (!$this->prepared) {
            return;
        }
        $this->prepared = false;

        $connection = $this->connectionPool->getConnectionForTable(KeSearchIndexRepository::TABLE_NAME);
        $statements = [
            'ALTER TABLE ' . KeSearchIndexRepository::TABLE_NAME . ' ENABLE KEYS',
            'DEALLOCATE PREPARE searchStmt',
            'DEALLOCATE PREPARE updateStmt',
            'DEALLOCATE PREPARE insertStmt',
        ];

        foreach ($statements as $statement) {
            try {
                $connection->executeStatement($statement);
            } catch (\Exception $exception) {
                $this->logger?->warning('Could not execute "' . $statement . '": ' . $exception->getMessage());
            }
        }
    }

    protected function prepare(): void
    {
        if ($this->prepared) {
            return;
        }

        // Same registration as \Tpwd\KeSearch\Indexer\IndexerRunner::startIndexing()
        $additionalFields = [];
        $additionalFieldHooks = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['registerAdditionalFields'] ?? null;
        if (is_array($additionalFieldHooks)) {
            foreach ($additionalFieldHooks as $className) {
                if (!is_string($className) || !class_exists($className)) {
                    continue;
                }
                $hookObject = GeneralUtility::makeInstance($className);
                if (method_exists($hookObject, 'registerAdditionalFields')) {
                    $hookObject->registerAdditionalFields($additionalFields);
                }
            }
        }

        $this->indexerRunner->additionalFields = array_values(array_unique(array_merge(
            (array) $this->indexerRunner->additionalFields,
            $additionalFields,
        )));

        $this->indexerRunner->prepareStatements();
        $this->prepared = true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildIndexerConfiguration(SiteInterface $site, Configuration $configuration): array
    {
        $indexerConfiguration = [
            'uid' => 0,
            'pid' => $configuration->storagePid,
            'title' => 'EXT:index (' . $site->getIdentifier() . ')',
            'type' => self::INDEXER_TYPE,
            'storagepid' => $configuration->storagePid,
            'targetpid' => $configuration->targetPid,
            'filteroption' => $configuration->filterOption,
            'index_use_page_tags_for_files' => 1,
            'sitehash' => $site->getIdentifier(),
        ];

        $event = new ModifyIndexerConfigurationEvent($indexerConfiguration, $site);
        $this->eventDispatcher->dispatch($event);

        return $event->indexerConfiguration;
    }
}
