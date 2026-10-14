<?php

namespace App\Services\AI\Tools;

use App\Services\MicrosoftGraphService;
use InvalidArgumentException;
use RuntimeException;

class FolderReaderTool implements Tool
{
    private const MAX_RESULTS = 100;

    public function __construct(
        private MicrosoftGraphService $graph
    ) {
    }

    public function name(): string
    {
        return 'sharepoint_list_folder';
    }

    public function description(): string
    {
        return 'List files and subfolders in a named folder in the HR-GARII SharePoint Documents library. Use a folder name or relative folder path.';
    }

    public function execute(array $arguments): mixed
    {
        $query = trim((string) ($arguments['query'] ?? ''));

        if ($query === '') {
            throw new InvalidArgumentException(
                'Provide a SharePoint folder name or path.'
            );
        }

        $site = $this->graph->getHrGariiSite();
        $siteId = $site['id'] ?? null;

        if (! is_string($siteId) || $siteId === '') {
            throw new RuntimeException(
                'Could not determine the SharePoint site ID.'
            );
        }

        $drives = $this->graph->getAllPages(
            'sites/' . rawurlencode($siteId) . '/drives'
        );

        $drive = collect($drives)->firstWhere('name', 'Documents');
        $driveId = $drive['id'] ?? null;

        if (! is_string($driveId) || $driveId === '') {
            throw new RuntimeException(
                'The SharePoint Documents library was not found.'
            );
        }

        $query = trim($query, " \t\n\r\0\x0B/");

        // Resolve the folder by walking its path from the library root.
        $segments = array_values(array_filter(
            explode('/', $query),
            fn (string $segment): bool => trim($segment) !== ''
        ));

        if ($segments === []) {
            throw new InvalidArgumentException(
                'Provide a valid folder name or path.'
            );
        }

        $parentId = null;
        $resolvedPath = [];

        foreach ($segments as $segment) {
            $endpoint = $parentId === null
                ? 'drives/' . rawurlencode($driveId) . '/root/children'
                : 'drives/' . rawurlencode($driveId)
                    . '/items/' . rawurlencode($parentId) . '/children';

            $items = $this->graph->getAllPages(
                $endpoint . '?$select=id,name,webUrl,folder,file,parentReference&$top=200'
            );

            $matches = array_values(array_filter(
                $items,
                fn (array $item): bool =>
                    isset($item['folder'])
                    && mb_strtolower((string) ($item['name'] ?? ''))
                        === mb_strtolower($segment)
            ));

            if (count($matches) !== 1) {
                return [
                    'success' => false,
                    'query' => $query,
                    'message' => count($matches) > 1
                        ? 'More than one folder matches this path segment. Provide the full folder path.'
                        : 'The folder was not found in the SharePoint Documents library.',
                    'items' => [],
                ];
            }

            $parentId = $matches[0]['id'] ?? null;

            if (! is_string($parentId) || $parentId === '') {
                throw new RuntimeException(
                    'The SharePoint folder metadata is incomplete.'
                );
            }

            $resolvedPath[] = $segment;
        }

        $endpoint = 'drives/' . rawurlencode($driveId)
            . '/items/' . rawurlencode($parentId)
            . '/children';

        $items = $this->graph->getAllPages(
            $endpoint . '?$select=id,name,webUrl,size,lastModifiedDateTime,folder,file&$top=200'
        );

        $results = [];

        foreach (array_slice($items, 0, self::MAX_RESULTS) as $item) {
            $name = (string) ($item['name'] ?? '');

            if ($name === '') {
                continue;
            }

            $isFolder = isset($item['folder']);
            $extension = pathinfo($name, PATHINFO_EXTENSION);

            $results[] = [
                'name' => $name,
                'type' => $isFolder ? 'folder' : 'file',
                'extension' => $isFolder || $extension === ''
                    ? null
                    : mb_strtolower($extension),
                'size' => isset($item['size'])
                    ? (int) $item['size']
                    : null,
                'webUrl' => $item['webUrl'] ?? null,
                'lastModifiedDateTime' =>
                    $item['lastModifiedDateTime'] ?? null,
            ];
        }

        return [
            'success' => true,
            'query' => $query,
            'folder' => implode('/', $resolvedPath),
            'count' => count($results),
            'truncated' => count($items) > self::MAX_RESULTS,
            'items' => $results,
            'message' => $results === []
                ? 'The folder exists but contains no items.'
                : 'Folder contents retrieved successfully.',
        ];
    }
}
