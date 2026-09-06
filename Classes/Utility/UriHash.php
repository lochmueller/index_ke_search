<?php

declare(strict_types=1);

namespace Lochmueller\IndexKeSearch\Utility;

/**
 * EXT:index identifies documents by their URI, ke_search identifies them by
 * "orig_uid + pid + type + language". This utility creates the deterministic values that connect
 * both worlds:
 *
 * - the "hash" column keeps the md5 of the URI, so a DeIndexDocumentEvent can find the record again
 * - the "orig_uid" column keeps a numeric fingerprint for documents without an own record uid
 *   (external pages and external files), because ke_search compares orig_uid as integer
 */
final class UriHash
{
    public static function hash(string $uri): string
    {
        return md5($uri);
    }

    public static function pseudoUid(string $uri): int
    {
        return (int) sprintf('%u', crc32($uri));
    }
}
