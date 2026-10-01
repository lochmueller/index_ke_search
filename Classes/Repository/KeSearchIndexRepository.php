<?php

declare(strict_types=1);

namespace Lochmueller\IndexKeSearch\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * Deletes bridge documents from the ke_search index.
 *
 * ke_search only offers deletion by "orig_uid + pid + type + language", which the bridge cannot use
 * for a DeIndexDocumentEvent, because that event only knows the URI of the document. The bridge
 * therefore stores the md5 of the URI in the unused "hash" column of page and external documents
 * (see \Lochmueller\IndexKeSearch\Utility\UriHash) and deletes by that value.
 */
class KeSearchIndexRepository
{
    public const TABLE_NAME = 'tx_kesearch_index';

    public function __construct(private readonly ConnectionPool $connectionPool) {}

    /**
     * @param string[] $types
     */
    public function deleteByUriHash(string $uriHash, int $storagePid, array $types): int
    {
        if ($uriHash === '' || $storagePid <= 0 || $types === []) {
            return 0;
        }

        $queryBuilder = $this->getQueryBuilder();

        return (int) $queryBuilder
            ->delete(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($storagePid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('hash', $queryBuilder->createNamedParameter($uriHash)),
                $queryBuilder->expr()->in('type', $queryBuilder->createNamedParameter($types, Connection::PARAM_STR_ARRAY)),
            )
            ->executeStatement();
    }

    /**
     * Removes every bridge document of the given storage folder that has not been written again
     * since $timestamp - these are the documents of pages and files that no longer exist.
     *
     * @param string[] $types        Types that are matched exactly (e.g. "page", "external")
     * @param string[] $typePrefixes Types that are matched by prefix (e.g. "file:")
     */
    public function deleteOutdated(int $storagePid, int $timestamp, array $types, array $typePrefixes = []): int
    {
        if ($storagePid <= 0 || $timestamp <= 0 || ($types === [] && $typePrefixes === [])) {
            return 0;
        }

        $queryBuilder = $this->getQueryBuilder();

        $typeConstraints = [];
        if ($types !== []) {
            $typeConstraints[] = $queryBuilder->expr()->in('type', $queryBuilder->createNamedParameter($types, Connection::PARAM_STR_ARRAY));
        }
        foreach ($typePrefixes as $typePrefix) {
            $typeConstraints[] = $queryBuilder->expr()->like(
                'type',
                $queryBuilder->createNamedParameter($queryBuilder->escapeLikeWildcards($typePrefix) . '%'),
            );
        }

        return (int) $queryBuilder
            ->delete(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($storagePid, Connection::PARAM_INT)),
                $queryBuilder->expr()->lt('tstamp', $queryBuilder->createNamedParameter($timestamp, Connection::PARAM_INT)),
                $queryBuilder->expr()->or(...$typeConstraints),
            )
            ->executeStatement();
    }

    protected function getQueryBuilder(): QueryBuilder
    {
        return $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);
    }
}
