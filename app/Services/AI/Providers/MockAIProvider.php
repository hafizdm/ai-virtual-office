<?php

namespace App\Services\AI\Providers;

use Illuminate\Support\Collection;

class MockAIProvider implements AIProvider
{
    public function chat(
        string $systemPrompt,
        Collection $messages,
        string $message
    ): string {
        // Tahap 2: format hasil tool yang sudah dijalankan.
        if (str_starts_with($message, 'Tool execution result:')) {
            return $this->formatToolResult($message);
        }

        $normalizedMessage = strtolower(trim($message));

        // Pertahankan pengujian mock_tool yang sudah ada.
        if (
            str_contains($normalizedMessage, 'gunakan mock tool')
            || str_contains($normalizedMessage, 'jalankan mock tool')
            || str_contains($normalizedMessage, 'test mock tool')
        ) {
            return $this->toolCall('mock_tool', [
                'message' => $message,
            ]);
        }

        // Identifikasi permintaan untuk membaca dokumen.
        if ($this->isDocumentReadRequest($normalizedMessage)) {
            $itemId = $this->extractItemId($message);

            $query = $this->extractDocumentQuery($message);

            if ($itemId !== null) {
                return $this->toolCall(
                    'sharepoint_read_document',
                    ['item_id' => $itemId]
                );
            }

            if ($query !== '') {
                return $this->toolCall(
                    'sharepoint_read_document',
                    ['query' => $query]
                );
            }

            return 'Sebutkan nama dokumen yang ingin Anda baca.';
        }

        // Identifikasi permintaan pencarian dokumen.
        if ($this->isDocumentSearchRequest($normalizedMessage)) {
            $query = $this->extractDocumentQuery($message);

            if ($query !== '') {
                return $this->toolCall('sharepoint_search', [
                    'query' => $query,
                ]);
            }

            return 'Sebutkan nama atau kata kunci dokumen yang ingin dicari.';
        }

        // Pertahankan fitur pengenalan nama pengguna.
        if (
            str_contains($normalizedMessage, 'siapa nama saya')
            || str_contains($normalizedMessage, 'nama saya siapa')
        ) {
            $name = $this->findName($messages);

            return $name
                ? "Nama Anda {$name}."
                : 'Saya belum mengetahui nama Anda.';
        }

        // Pertahankan pemeriksaan system prompt.
        if (
            str_contains($normalizedMessage, 'system prompt')
            || str_contains($normalizedMessage, 'instruksi sistem')
        ) {
            return 'System instruction berhasil diterima oleh AI Provider.';
        }

        // Pertahankan pemeriksaan conversation history.
        if (
            str_contains($normalizedMessage, 'ingat')
            || str_contains($normalizedMessage, 'history')
            || str_contains($normalizedMessage, 'riwayat')
        ) {
            return sprintf(
                'Saya menerima %d pesan sebelumnya dalam conversation ini.',
                $messages->count()
            );
        }

        return 'Saya menerima pesan Anda. System instruction dan conversation history sudah berhasil diterima.';
    }

    private function toolCall(
        string $tool,
        array $arguments
    ): string {
        return json_encode([
            'type' => 'tool_call',
            'tool' => $tool,
            'arguments' => $arguments,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ?: 'Gagal membuat keputusan tool.';
    }

    private function isDocumentReadRequest(string $message): bool
    {
        foreach ([
            'baca dokumen',
            'bacakan dokumen',
            'isi dokumen',
            'ringkas dokumen',
            'rangkum dokumen',
            'jelaskan dokumen',
            'summarize document',
            'summarize the document',
            'read document',
            'read the document',
        ] as $phrase) {
            if (str_contains($message, $phrase)) {
                return true;
            }
        }

        return false;
    }

    private function isDocumentSearchRequest(string $message): bool
    {
        foreach ([
            'cari dokumen',
            'carikan dokumen',
            'temukan dokumen',
            'tampilkan dokumen',
            'daftar dokumen',
            'search document',
            'search for document',
            'find document',
            'find the document',
            'cari file',
            'temukan file',
        ] as $phrase) {
            if (str_contains($message, $phrase)) {
                return true;
            }
        }

        return false;
    }

    private function extractDocumentQuery(string $message): string
    {
        // Hilangkan ID eksplisit agar tidak dianggap sebagai nama dokumen.
        $message = preg_replace(
            '/\bitem_id\s*[:=]?\s*[a-zA-Z0-9_-]+/i',
            '',
            $message
        ) ?? $message;

        $query = trim($message);

        $query = preg_replace(
            '/^(tolong\s+)?(coba\s+)?'
            . '(carikan|cari|temukan|tampilkan|daftar|'
            . 'baca|bacakan|ringkas|rangkum|jelaskan|'
            . 'search|find|read|summarize)\s+/iu',
            '',
            $query
        ) ?? $query;

        $query = preg_replace(
            '/^(dokumen|file)\s+/iu',
            '',
            $query
        ) ?? $query;

        $query = preg_replace(
            '/\s+(di|dalam|pada)\s+sharepoint.*$/iu',
            '',
            $query
        ) ?? $query;

        return trim($query, " \t\n\r\0\x0B.,?!:;\"'");
    }

    private function extractItemId(string $message): ?string
    {
        if (! preg_match(
            '/\bitem_id\s*[:=]?\s*["\']?([a-zA-Z0-9_-]{20,})["\']?/i',
            $message,
            $matches
        )) {
            return null;
        }

        return $matches[1];
    }

    private function formatToolResult(string $message): string
    {
        $pattern = '/Tool:\s*(.+)\RResult:\s*(.+)$/s';

        if (! preg_match($pattern, trim($message), $matches)) {
            return 'Hasil eksekusi tool diterima, tetapi formatnya tidak dikenali.';
        }

        $toolName = trim($matches[1]);
        $result = json_decode(trim($matches[2]), true);

        if (! is_array($result)) {
            return 'Tool telah dijalankan, tetapi hasilnya tidak dapat dibaca.';
        }

        if (
            $toolName === 'mock_tool'
            && ($result['success'] ?? false) === true
        ) {
            return 'Mock tool berhasil dijalankan. Hasil: '
                . ($result['message'] ?? 'Tidak ada pesan hasil.');
        }

        if ($toolName === 'sharepoint_search') {
            return $this->formatSearchResult($result);
        }

        if ($toolName === 'sharepoint_read_document') {
            return $this->formatDocumentResult($result);
        }

        return 'Hasil eksekusi tool diterima: '
            . json_encode(
                $result,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
    }

    private function formatSearchResult(array $result): string
    {
        $documents = $result['documents'] ?? [];

        if (! is_array($documents) || $documents === []) {
            return 'Tidak ditemukan dokumen yang cocok dalam indeks SharePoint. '
                . 'Kata kunci: ' . ($result['query'] ?? '-');
        }

        $lines = [
            'Hasil pencarian SharePoint:',
            'Jumlah hasil: ' . count($documents),
        ];

        foreach ($documents as $index => $document) {
            $lines[] = sprintf(
                "%d. %s\n   Folder: %s\n   Format: %s\n   Item ID: %s\n   Link: %s",
                $index + 1,
                $document['name'] ?? 'Nama tidak tersedia',
                $document['path'] ?? '-',
                $document['extension'] ?? '-',
                $document['id'] ?? '-',
                $document['webUrl'] ?? '-'
            );
        }

        $lines[] = 'Catatan: hasil ini berasal dari indeks metadata SharePoint.';

        return implode("\n", $lines);
    }

    private function formatDocumentResult(array $result): string
    {
        if (($result['requires_selection'] ?? false) === true) {
            $lines = [
                'Ditemukan beberapa dokumen yang cocok. '
                . 'Pengguna perlu memilih dokumen yang benar:',
            ];

            foreach ($result['documents'] ?? [] as $index => $document) {
                $lines[] = sprintf(
                    "%d. %s\n   Folder: %s\n   Item ID: %s",
                    $index + 1,
                    $document['name'] ?? 'Nama tidak tersedia',
                    $document['path'] ?? '-',
                    $document['id'] ?? '-'
                );
            }

            return implode("\n", $lines);
        }

        if (($result['success'] ?? false) !== true) {
            return 'Dokumen belum berhasil dibaca. '
                . ($result['message'] ?? 'Tidak ada detail tambahan.');
        }

        return sprintf(
            "Dokumen berhasil dibaca: %s\nFolder: %s\nLink: %s\n\nIsi dokumen yang berhasil diekstrak:\n%s",
            $result['document'] ?? 'Nama tidak tersedia',
            $result['path'] ?? '-',
            $result['webUrl'] ?? '-',
            $result['text'] ?? ''
        );
    }

    private function findName(Collection $messages): ?string
    {
        foreach ($messages as $message) {
            if ($message->role !== 'user') {
                continue;
            }

            $content = trim($message->content);

            if (preg_match(
                '/(?:nama saya adalah|nama saya)\s+([a-zA-Z][a-zA-Z\s]{1,50})/i',
                $content,
                $matches
            )) {
                return trim($matches[1]);
            }
        }

        return null;
    }
}