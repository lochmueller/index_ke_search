<?php

declare(strict_types=1);

namespace Lochmueller\IndexKeSearch\Mapper;

use Lochmueller\IndexKeSearch\Dto\FileInformation;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;

/**
 * Resolves the FAL information of an EXT:index file identifier into the values ke_search
 * expects in its index record.
 */
class FileInformationResolver implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(private readonly ResourceFactory $resourceFactory) {}

    /**
     * Returns null if the identifier is empty or cannot be resolved - in that case the document
     * has to be indexed as "external" document, because ke_search cannot build a file link for it.
     */
    public function resolve(string $fileIdentifier): ?FileInformation
    {
        if (trim($fileIdentifier) === '') {
            return null;
        }

        try {
            $file = $this->resourceFactory->getFileObjectFromCombinedIdentifier($fileIdentifier);
            if (!$file instanceof File) {
                return null;
            }

            $localPath = $file->getForLocalProcessing(false);
            $properties = $file->getProperties();

            return new FileInformation(
                uid: $file->getUid(),
                name: $file->getName(),
                extension: strtolower($file->getExtension()),
                directory: $localPath,
                hash: $this->buildHash($localPath, $file->getName()),
                modificationTime: $this->resolveModificationTime($localPath, $file),
                feGroups: (string) ($properties['fe_groups'] ?? ''),
                description: (string) ($properties['description'] ?? ''),
            );
        } catch (\Exception $exception) {
            $this->logger?->warning(
                'Could not resolve file "' . $fileIdentifier . '" for the ke_search index: ' . $exception->getMessage(),
                ['exception' => $exception],
            );
            return null;
        }
    }

    /**
     * Same hash calculation as \Tpwd\KeSearch\Indexer\Types\File::getUniqueHashForFile(), so that
     * documents of this bridge and documents of the ke_search file indexer do not create duplicates.
     */
    protected function buildHash(string $localPath, string $name): string
    {
        return md5($localPath . $name);
    }

    /**
     * ke_search prefers the modification time of the file system over the FAL value, so that files
     * which have been changed without FAL noticing it (e.g. an FTP upload) are re-indexed.
     */
    protected function resolveModificationTime(string $localPath, File $file): int
    {
        $modificationTime = @filemtime($localPath);

        return $modificationTime === false ? $file->getModificationTime() : $modificationTime;
    }
}
