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
        $normalizedMessage = strtolower(trim($message));

        /*
         * Tahap 2: tangani hasil tool yang dikirim kembali
         * oleh GeneralAgent.
         */
        if (str_starts_with($message, 'Tool execution result:')) {
            return $this->formatToolResult($message);
        }

        /*
         * Simulasi keputusan AI untuk menjalankan mock_tool.
         * Ini adalah perilaku development, bukan penalaran AI.
         */
        if (
            str_contains($normalizedMessage, 'gunakan mock tool')
            || str_contains($normalizedMessage, 'jalankan mock tool')
            || str_contains($normalizedMessage, 'test mock tool')
        ) {
            return json_encode([
                'type' => 'tool_call',
                'tool' => 'mock_tool',
                'arguments' => [
                    'message' => $message,
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                ?: 'Gagal membuat keputusan tool.';
        }

        /*
         * Pertanyaan mengenai nama pengguna.
         */
        if (
            str_contains($normalizedMessage, 'siapa nama saya')
            || str_contains($normalizedMessage, 'nama saya siapa')
        ) {
            $name = $this->findName($messages);

            return $name
                ? "Nama Anda {$name}."
                : 'Saya belum mengetahui nama Anda.';
        }

        /*
         * Pemeriksaan system prompt.
         */
        if (
            str_contains($normalizedMessage, 'system prompt')
            || str_contains($normalizedMessage, 'instruksi sistem')
        ) {
            return 'System instruction berhasil diterima oleh AI Provider.';
        }

        /*
         * Pemeriksaan conversation history.
         */
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

        return 'Hasil eksekusi tool diterima: '
            . json_encode(
                $result,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
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
