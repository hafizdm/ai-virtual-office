<?php

namespace App\Services\AI\Tools;

use App\Services\MicrosoftGraphService;
use InvalidArgumentException;
use RuntimeException;


class SharePointSearchTool implements Tool
{
    private const MAX_DEPTH = 8;
    private const MAX_SCANNED_ITEMS = 1000;

    private int $scannedItems = 0;

    public function __construct(
        private MicrosoftGraphService $graph
    ) {
    }

    public function name(): string
    {
        return 'sharepoint_search';
    }

    public function description(): string
    {
        return 'Search filenames recursively in the HR-GARII SharePoint Documents library.';
    }

    public function execute(array $arguments): mixed
    {
        $query = trim((string) ($arguments['query'] ?? ''));

        if ($query === '') {
            throw new InvalidArgumentException(
                'Search query must not be empty.'
            );
        }

        $site = $this->graph->getHrGariiSite();
        $siteId = $site['id'] ?? null;

        if (!is_string($siteId) || $siteId === '') {
            throw new RuntimeException(
                'Could not determine the HR-GARII site ID.'
            );
        }

        $drives = $this->graph->get(
            'sites/' . rawurlencode($siteId) . '/drives'
        );

        $driveId = null;

        foreach ($drives['value'] ?? [] as $drive) {
            if (($drive['name'] ?? '') === 'Documents') {
                $driveId = $drive['id'] ?? null;
                break;
            }
        }

        if (!is_string($driveId) || $driveId === '') {
            throw new RuntimeException(
                'The Documents library was not found in HR-GARII.'
            );
        }

        $this->scannedItems = 0;
        $matches = [];

        $this->searchFolder(
            $driveId,
            null,
            $query,
            0,
            '',
            $matches
        );

        return [
            'success' => true,
            'site' => $site['displayName'] ?? 'HR-GARII',
            'library' => 'Documents',
            'query' => $query,
            'count' => count($matches),
            'scannedItems' => $this->scannedItems,
            'limitReached' => $this->scannedItems >= self::MAX_SCANNED_ITEMS,
            'documents' => $matches,
        ];
    }

   
    private function searchFolder(
        string $driveId,
        ?string $folderId,
        string $query,
        int $depth,
        string $parentPath,
        array &$matches
    ): void {
        if (
            $depth > self::MAX_DEPTH ||
            $this->scannedItems >= self::MAX_SCANNED_ITEMS
        ) {
            return;
        }

        if ($folderId === null) {
            $endpoint = 'drives/' . rawurlencode($driveId)
                . '/root/children?$select=id,name,webUrl,size,lastModifiedDateTime,file,folder&$top=200';
        } else {
            $endpoint = 'drives/' . rawurlencode($driveId)
                . '/items/' . rawurlencode($folderId)
                . '/children?$select=id,name,webUrl,size,lastModifiedDateTime,file,folder&$top=200';
        }

        $result = $this->graph->get($endpoint);
        $folders = [];
        $needle = mb_strtolower($query);

        // Pass 1: inspect current folder files before entering subfolders.
        foreach ($result['value'] ?? [] as $item) {
            if ($this->scannedItems >= self::MAX_SCANNED_ITEMS) {
                break;
            }

            $this->scannedItems++;

            $name = (string) ($item['name'] ?? '');
            $path = $parentPath === ''
                ? $name
                : $parentPath . '/' . $name;

            if (isset($item['folder'])) {
                $folders[] = [
                    'id' => $item['id'] ?? null,
                    'path' => $path,
                ];

                continue;
            }

            if (
                isset($item['file']) &&
                str_contains(mb_strtolower($name), $needle)
            ) {
                $matches[] = [
                    'id' => $item['id'] ?? null,
                    'name' => $name,
                    'path' => $path,
                    'webUrl' => $item['webUrl'] ?? null,
                    'size' => $item['size'] ?? null,
                    'lastModifiedDateTime' =>
                        $item['lastModifiedDateTime'] ?? null,
                ];
            }
        }

        // Pass 2: recursively inspect subfolders.
        foreach ($folders as $folder) {
            if ($this->scannedItems >= self::MAX_SCANNED_ITEMS) {
                return;
            }

            if (!is_string($folder['id']) || $folder['id'] === '') {
                continue;
            }

            $this->searchFolder(
                $driveId,
                $folder['id'],
                $query,
                $depth + 1,
                $folder['path'],
                $matches
            );
        }
    }

}
