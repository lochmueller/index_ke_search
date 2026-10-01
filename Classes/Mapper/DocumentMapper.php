<?php

declare(strict_types=1);

namespace Lochmueller\IndexKeSearch\Mapper;

use Lochmueller\Index\Event\IndexFileEvent;
use Lochmueller\Index\Event\IndexPageEvent;
use Lochmueller\Index\Traversing\RecordSelection;
use Lochmueller\Index\Utility\AccessGroupParser;
use Lochmueller\IndexKeSearch\Configuration\Configuration;
use Lochmueller\IndexKeSearch\Dto\IndexDocument;
use Lochmueller\IndexKeSearch\Utility\UriHash;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Tpwd\KeSearch\Lib\SearchHelper;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Maps the indexing events of EXT:index to ke_search index records.
 *
 * ke_search derives the result link from the "type" column
 * (see \Tpwd\KeSearch\Lib\SearchHelper::getResultLinkConfiguration()):
 *
 * - "page"    -> typolink to "targetpid" (+ "params" as additional parameters)
 * - "file:%"  -> t3://file?uid=<orig_uid>
 * - "external"-> typolink to the URI in "params"
 *
 * That is why documents with a page uid become "page" documents, FAL files become "file:<ext>"
 * documents and everything else (external pages, remote files) becomes an "external" document.
 */
class DocumentMapper implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * ke_search treats "-1" as "valid in every language". EXT:index does not provide a language
     * for files, so file documents are indexed for all languages.
     */
    public const LANGUAGE_ALL = -1;

    public function __construct(
        private readonly FileInformationResolver $fileInformationResolver,
        private readonly RecordSelection         $recordSelection,
    ) {}

    public function map(IndexPageEvent|IndexFileEvent $event, Configuration $configuration): ?IndexDocument
    {
        return $event instanceof IndexPageEvent
            ? $this->mapPage($event, $configuration)
            : $this->mapFile($event, $configuration);
    }

    protected function mapPage(IndexPageEvent $event, Configuration $configuration): ?IndexDocument
    {
        $uri = $this->resolveUri($event);
        $isExternal = $event->pageUid <= 0;

        if ($isExternal && $uri === '') {
            $this->logger?->warning('Skipping page document without page uid and without URI', [
                'indexProcessId' => $event->indexProcessId,
                'title' => $event->title,
            ]);
            return null;
        }

        $pageRow = $isExternal ? null : $this->recordSelection->findRenderablePage($event->pageUid, $event->language);

        $tags = '';
        if ($configuration->pageKeywordsAsTags && isset($pageRow['keywords'])) {
            SearchHelper::makeTags($tags, GeneralUtility::trimExplode(',', (string) $pageRow['keywords'], true));
        }

        return new IndexDocument(
            storagePid: $configuration->storagePid,
            title: $event->title,
            type: $isExternal ? $configuration->externalType : $configuration->pageType,
            targetPid: (string) ($isExternal ? $configuration->targetPid : $event->pageUid),
            content: $this->normalizeContent($event->content),
            tags: $tags,
            params: $isExternal ? $uri : '',
            abstract: $this->resolveAbstract($pageRow),
            language: $event->language,
            starttime: (int) ($pageRow['starttime'] ?? 0),
            endtime: (int) ($pageRow['endtime'] ?? 0),
            feGroup: AccessGroupParser::format($event->accessGroups),
            additionalFields: [
                'orig_uid' => $isExternal ? UriHash::pseudoUid($uri) : $event->pageUid,
                'orig_pid' => (int) ($pageRow['pid'] ?? 0),
                'sortdate' => $this->resolveSortDate($pageRow),
                'directory' => '',
                'hash' => UriHash::hash($uri),
            ],
        );
    }

    protected function mapFile(IndexFileEvent $event, Configuration $configuration): ?IndexDocument
    {
        $fileInformation = $this->fileInformationResolver->resolve($event->fileIdentifier);

        // No FAL file behind the document (e.g. an externally indexed remote file). ke_search
        // cannot build a file link for it, so it is stored as external document with its URI.
        if ($fileInformation === null) {
            if ($event->uri === '') {
                $this->logger?->warning('Skipping file document without FAL file and without URI', [
                    'indexProcessId' => $event->indexProcessId,
                    'title' => $event->title,
                ]);
                return null;
            }

            return new IndexDocument(
                storagePid: $configuration->storagePid,
                title: $event->title,
                type: $configuration->externalType,
                targetPid: (string) $configuration->targetPid,
                content: $this->normalizeContent($event->content),
                params: $event->uri,
                language: self::LANGUAGE_ALL,
                additionalFields: [
                    'orig_uid' => UriHash::pseudoUid($event->uri),
                    'orig_pid' => 0,
                    'sortdate' => 0,
                    'directory' => '',
                    'hash' => UriHash::hash($event->uri),
                ],
            );
        }

        $tags = '';
        SearchHelper::makeTags($tags, ['file']);

        return new IndexDocument(
            storagePid: $configuration->storagePid,
            title: $event->title !== '' ? $event->title : $fileInformation->name,
            type: 'file:' . $fileInformation->extension,
            targetPid: (string) $configuration->targetPid,
            content: $this->normalizeContent($event->content),
            tags: $tags,
            abstract: $fileInformation->description,
            language: self::LANGUAGE_ALL,
            feGroup: $fileInformation->feGroups,
            additionalFields: [
                'orig_uid' => $fileInformation->uid,
                'orig_pid' => 0,
                'sortdate' => $fileInformation->modificationTime,
                'directory' => $fileInformation->directory,
                'hash' => $fileInformation->hash,
            ],
        );
    }

    /**
     * EXT:index does not always deliver a URI (e.g. for the database indexing technology), so it is
     * rebuilt from the site router in that case.
     */
    protected function resolveUri(IndexPageEvent $event): string
    {
        if ($event->uri !== '') {
            return $event->uri;
        }
        if ($event->pageUid <= 0 || !$event->site instanceof Site) {
            return '';
        }

        $arguments = [];
        try {
            $arguments['_language'] = $event->site->getLanguageById($event->language);
        } catch (\InvalidArgumentException) {
            // Site without that language - build the URI in the default language
        }

        try {
            return (string) $event->site->getRouter()->generateUri($event->pageUid, $arguments);
        } catch (\Exception $exception) {
            $this->logger?->warning('Could not build the URI of page ' . $event->pageUid, ['exception' => $exception]);
            return '';
        }
    }

    /**
     * @param array<string, mixed>|null $pageRow
     */
    protected function resolveAbstract(?array $pageRow): string
    {
        foreach (['tx_kesearch_abstract', 'abstract', 'description'] as $field) {
            if (trim((string) ($pageRow[$field] ?? '')) !== '') {
                return (string) $pageRow[$field];
            }
        }
        return '';
    }

    /**
     * Same priority as \Tpwd\KeSearch\Hooks\AdditionalFields::modifyPagesIndexEntry().
     *
     * @param array<string, mixed>|null $pageRow
     */
    protected function resolveSortDate(?array $pageRow): int
    {
        foreach (['lastUpdated', 'SYS_LASTCHANGED', 'tstamp', 'crdate'] as $field) {
            if ((int) ($pageRow[$field] ?? 0) > 0) {
                return (int) $pageRow[$field];
            }
        }
        return 0;
    }

    protected function normalizeContent(string $content): string
    {
        return trim((string) preg_replace('/\s+/', ' ', strip_tags($content)));
    }
}
