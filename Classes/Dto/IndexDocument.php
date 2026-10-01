<?php

declare(strict_types=1);

namespace Lochmueller\IndexKeSearch\Dto;

/**
 * Normalized representation of one ke_search index record.
 *
 * The property names and their semantics follow the argument list of
 * \Tpwd\KeSearch\Indexer\IndexerRunner::storeInIndex().
 */
final class IndexDocument
{
    /**
     * @param string                   $targetPid       Page uid the result links to (string, because ke_search allows typolink syntax)
     * @param int                      $language        Language uid, -1 means "all languages"
     * @param array<string, mixed>     $additionalFields Additional index columns (orig_uid, orig_pid, sortdate, directory, hash, ...)
     */
    public function __construct(
        public int    $storagePid,
        public string $title,
        public string $type,
        public string $targetPid,
        public string $content,
        public string $tags = '',
        public string $params = '',
        public string $abstract = '',
        public int    $language = 0,
        public int    $starttime = 0,
        public int    $endtime = 0,
        public string $feGroup = '',
        public array  $additionalFields = [],
    ) {}
}
