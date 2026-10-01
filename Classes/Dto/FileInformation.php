<?php

declare(strict_types=1);

namespace Lochmueller\IndexKeSearch\Dto;

/**
 * The subset of FAL information that is needed to build a ke_search "file:*" index record.
 */
final readonly class FileInformation
{
    public function __construct(
        public int    $uid,
        public string $name,
        public string $extension,
        public string $directory,
        public string $hash,
        public int    $modificationTime,
        public string $feGroups = '',
        public string $description = '',
    ) {}
}
