<?php

namespace App\Services\AI\Tools;

use App\Models\SharePointDocument;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class SharePointSearchTool implements Tool
{
    public function name(): string
    {
        return 'sharepoint_search';
    }

    public function description(): string
    {
        return 'Search SharePoint documents by individual keywords in filenames and folder paths. Returns matching document metadata and SharePoint links.';
    }

    public function execute(array $arguments): mixed
    {
        $query = trim((string) ($arguments['query'] ?? ''));

        if ($query === '') {
            throw new InvalidArgumentException(
                'Search query must not be empty.'
            );
        }

        // Split a natural-language query into individual keywords.
        $keywords = preg_split('/\s+/u', mb_strtolower($query)) ?: [];

        $keywords = array_values(array_unique(array_filter(
            $keywords,
            fn (string $word): bool =>
                mb_strlen($word) >= 3
                && !in_array($word, [
                    'yang', 'dan', 'atau', 'dari', 'untuk',
                    'dengan', 'file', 'dokumen', 'cari',
                    'tolong', 'saya', 'ingin', 'lokasi',
                    'sharepoint', 'tentang', 'pada', 'the',
                    'and', 'for', 'with',
                ], true)
        )));

        if (empty($keywords)) {
            $keywords = [mb_strtolower($query)];
        }

        // Match any keyword in either the filename or folder path.
        $documents = SharePointDocument::query()
            ->where(function (Builder $outer) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $pattern = '%' . $this->escapeLike($keyword) . '%';

                    $outer->orWhere(function (Builder $inner) use ($pattern) {
                        $inner->where('name', 'like', $pattern)
                            ->orWhere('path', 'like', $pattern);
                    });
                }
            })
            ->limit(200)
            ->get([
                'site_id',
                'drive_id',
                'item_id',
                'name',
                'extension',
                'mime_type',
                'size',
                'path',
                'web_url',
                'last_modified_at',
            ]);

        // Rank documents by how many keywords match their metadata.
        $rankedDocuments = $documents
            ->map(function (SharePointDocument $document) use ($keywords) {
                $name = mb_strtolower((string) $document->name);
                $path = mb_strtolower((string) $document->path);

                $score = 0;

                foreach ($keywords as $keyword) {
                    if (str_contains($name, $keyword)) {
                        $score += 3;
                    }

                    if (str_contains($path, $keyword)) {
                        $score += 1;
                    }
                }

                $document->search_score = $score;

                return $document;
            })
            ->sortByDesc('search_score')
            ->take(50)
            ->values();

        return [
            'success' => true,
            'source' => 'local_index',
            'library' => 'Documents',
            'query' => $query,
            'keywords' => $keywords,
            'count' => $rankedDocuments->count(),
            'limit' => 50,
            'message' => $rankedDocuments->isEmpty()
                ? 'No matching documents were found in the local index. The document may not be indexed yet.'
                : 'Search completed using keyword matching against the local SharePoint metadata index.',
            'documents' => $rankedDocuments
                ->map(fn (SharePointDocument $document) => [
                    'id' => $document->item_id,
                    'name' => $document->name,
                    'extension' => $document->extension,
                    'mimeType' => $document->mime_type,
                    'size' => $document->size,
                    'path' => $document->path,
                    'webUrl' => $document->web_url,
                    'lastModifiedDateTime' =>
                        $document->last_modified_at?->toISOString(),
                ])
                ->all(),
        ];
    }

    private function escapeLike(string $value): string
    {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $value
        );
    }
}
