<?php

namespace App\Services;

use App\Models\SharePointDocument;
use Carbon\Carbon;
use RuntimeException;

class SharePointDocumentSyncService
{
    public function __construct(
        private MicrosoftGraphService $graph
    ) {
    }

    /**
     * Synchronize document metadata from the SharePoint Documents library.
     *
     * @return array{
     *     success: bool,
     *     site: string,
     *     library: string,
     *     scannedItems: int,
     *     filesProcessed: int,
     *     created: int,
     *     updated: int
     * }
     */
    public function sync(): array
    {
        $site = $this->graph->getHrGariiSite();
        $siteId = $site['id'] ?? null;

        if (! is_string($siteId) || $siteId === '') {
            throw new RuntimeException(
                'Could not determine the HR-GARII site ID.'
            );
        }

        $drives = $this->graph->getAllPages(
            'sites/' . rawurlencode($siteId) . '/drives'
        );

        $drive = collect($drives)->firstWhere('name', 'Documents');

        $driveId = $drive['id'] ?? null;

        if (! is_string($driveId) || $driveId === '') {
            throw new RuntimeException(
                'The Documents library was not found in HR-GARII.'
            );
        }

        $scannedItems = 0;
        $filesProcessed = 0;
        $created = 0;
        $updated = 0;
        $syncedAt = now();

        // Traverse the library using a stack to avoid recursive PHP calls.
        $foldersToVisit = [
            [
                'id' => null,
                'path' => '',
            ],
        ];

        while ($foldersToVisit !== []) {
            $currentFolder = array_pop($foldersToVisit);

            if ($currentFolder['id'] === null) {
                $endpoint = 'drives/' . rawurlencode($driveId)
                    . '/root/children'
                    . '?$select=id,name,webUrl,size,lastModifiedDateTime,eTag,file,folder,parentReference'
                    . '&$top=200';
            } else {
                $endpoint = 'drives/' . rawurlencode($driveId)
                    . '/items/' . rawurlencode($currentFolder['id'])
                    . '/children'
                    . '?$select=id,name,webUrl,size,lastModifiedDateTime,eTag,file,folder,parentReference'
                    . '&$top=200';
            }

            foreach ($this->graph->iteratePages($endpoint) as $items) {
                foreach ($items as $item) {
                    $scannedItems++;

                    $name = (string) ($item['name'] ?? '');
                    $itemId = $item['id'] ?? null;

                    $path = $currentFolder['path'] === ''
                        ? $name
                        : $currentFolder['path'] . '/' . $name;

                    // Index folders for traversal, but do not store them as documents.
                    if (isset($item['folder'])) {
                        if (
                            is_string($itemId)
                            && $itemId !== ''
                        ) {
                            $foldersToVisit[] = [
                                'id' => $itemId,
                                'path' => $path,
                            ];
                        }

                        continue;
                    }

                    // Only index files with valid Microsoft Graph identifiers.
                    if (
                        ! isset($item['file'])
                        || ! is_string($itemId)
                        || $itemId === ''
                        || $name === ''
                    ) {
                        continue;
                    }

                    $extension = pathinfo(
                        $name,
                        PATHINFO_EXTENSION
                    );

                    $mimeType = $item['file']['mimeType'] ?? null;

                    $modifiedAt = null;

                    if (! empty($item['lastModifiedDateTime'])) {
                        $modifiedAt = Carbon::parse(
                            $item['lastModifiedDateTime']
                        );
                    }

                    $attributes = [
                        'site_id' => $siteId,
                        'drive_id' => $driveId,
                        'item_id' => $itemId,
                        'name' => $name,
                        'extension' => $extension !== ''
                            ? mb_strtolower($extension)
                            : null,
                        'mime_type' => is_string($mimeType)
                            ? $mimeType
                            : null,
                        'size' => isset($item['size'])
                            ? (int) $item['size']
                            : null,
                        'path' => $path,
                        'web_url' => $item['webUrl'] ?? null,
                        'parent_item_id' =>
                            $item['parentReference']['id'] ?? null,
                        'last_modified_at' => $modifiedAt,
                        'etag' => $item['eTag'] ?? null,
                        'last_synced_at' => $syncedAt,
                    ];

                    $document = SharePointDocument::firstOrNew([
                        'drive_id' => $driveId,
                        'item_id' => $itemId,
                    ]);

                    $wasRecentlyCreated = ! $document->exists;

                    $document->fill($attributes);
                    $document->save();

                    if ($wasRecentlyCreated) {
                        $created++;
                    } else {
                        $updated++;
                    }

                    $filesProcessed++;
                }
            }
        }

        return [
            'success' => true,
            'site' => $site['displayName'] ?? 'HR-GARII',
            'library' => 'Documents',
            'scannedItems' => $scannedItems,
            'filesProcessed' => $filesProcessed,
            'created' => $created,
            'updated' => $updated,
        ];
    }
}
