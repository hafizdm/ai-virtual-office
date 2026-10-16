<?php

namespace App\Services\AI\Agents;

use App\Services\AI\Instructions\GeneralAgentInstructions;
use App\Services\AI\Providers\AIProvider;
use App\Services\AI\ToolExecutor;
use App\Services\AI\ToolRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

class GeneralAgent
{
    public function __construct(
        private AIProvider $provider,
        private GeneralAgentInstructions $instructions,
        private ToolRegistry $toolRegistry,
        private ToolExecutor $toolExecutor
    ) {
    }

    public function run(
        Collection $messages,
        string $message
    ): string {
        $agentStartedAt = microtime(true);
        $systemPrompt = $this->buildSystemPrompt();

        $response = $this->provider->chat(
            $systemPrompt,
            $messages,
            $message
        );

        $decision = $this->parseToolDecision($response);

        if ($decision === null) {
            Log::info('AI Agent completed without tool', [
                'duration_ms' => (int) round(
                    (microtime(true) - $agentStartedAt) * 1000
                ),
                'history_messages' => $messages->count(),
            ]);

            return $response;
        }

        $toolName = $decision['tool'];
        $arguments = $decision['arguments'];

        $toolStartedAt = microtime(true);

        try {
            $result = $this->toolExecutor->execute(
                $toolName,
                $arguments
            );
        } catch (Throwable $exception) {
            Log::error('AI Agent tool execution failed', [
                'tool' => $toolName,
                'duration_ms' => (int) round(
                    (microtime(true) - $toolStartedAt) * 1000
                ),
                'exception' => get_class($exception),
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        Log::info('AI Agent tool execution completed', [
            'tool' => $toolName,
            'duration_ms' => (int) round(
                (microtime(true) - $toolStartedAt) * 1000
            ),
        ]);

        // Return SharePoint search results directly.
        // Avoid sending large metadata results to Ollama again.
        if (
            $toolName === 'sharepoint_search'
            && is_array($result)
            && ($result['success'] ?? false) === true
        ) {
            $documents = $result['documents'] ?? [];
            $query = (string) ($result['query'] ?? '');

            if (empty($documents)) {
                return "Belum ditemukan dokumen yang cocok untuk pencarian: "
                    . $query
                    . ". Dokumen mungkin belum masuk indeks lokal "
                    . "atau kata kunci perlu diperbaiki.";
            }

            $response = "Hasil pencarian dokumen SharePoint\n";
            $response .= "Kata kunci: {$query}\n";
            $response .= "Jumlah hasil: " . count($documents) . "\n\n";

            foreach ($documents as $index => $document) {
                $number = $index + 1;
                $name = (string) (
                    $document['name'] ?? 'Nama tidak tersedia'
                );
                $path = (string) (
                    $document['path'] ?? 'Lokasi tidak tersedia'
                );
                $extension = (string) ($document['extension'] ?? '');

                if ($extension === '') {
                    $extension = pathinfo($name, PATHINFO_EXTENSION);
                }

                $url = (string) ($document['webUrl'] ?? '');

                $response .= "{$number}. {$name}\n";
                $response .= "Lokasi: {$path}\n";
                $response .= "Tipe file: "
                    . (
                        $extension !== ''
                            ? strtoupper($extension)
                            : 'Tidak diketahui'
                    )
                    . "\n";

                if ($url !== '') {
                    $response .= "Tautan SharePoint: {$url}\n";
                }

                $response .= "\n";
            }

            Log::info(
                'AI Agent returned SharePoint search results directly',
                [
                    'tool' => $toolName,
                    'query' => $query,
                    'result_count' => count($documents),
                ]
            );

            return trim($response);
        }

        // Return extracted XLSX content directly.
        // Avoid a second, potentially expensive Ollama request.
        if (
            $toolName === 'sharepoint_read_document'
            && is_array($result)
            && ($result['success'] ?? false) === true
            && strtolower(
                (string) ($result['extension'] ?? '')
            ) === 'xlsx'
        ) {
            $documentName = (string) (
                $result['document'] ?? 'Unknown'
            );
            $documentPath = (string) (
                $result['path'] ?? 'Not available'
            );
            $text = trim((string) ($result['text'] ?? ''));

            if ($text !== '') {
                $response = "Dokumen Excel berhasil dibaca.\n\n";
                $response .= "Nama file: {$documentName}\n";
                $response .= "Lokasi: {$documentPath}\n\n";
                $response .= "Hasil ekstraksi:\n\n";
                $response .= $text;

                if (($result['truncated'] ?? false) === true) {
                    $response .= "\n\nCatatan: hasil ekstraksi dipotong "
                        . "karena batas panjang teks yang dikonfigurasi.";
                }

                Log::info(
                    'AI Agent returned XLSX extraction directly',
                    [
                        'tool' => $toolName,
                        'document' => $documentName,
                        'text_length' => mb_strlen($text),
                    ]
                );

                return $response;
            }
        }

        // Return SharePoint folder contents directly.
        // Avoid a second, potentially expensive Ollama request.
        if (
            $toolName === 'sharepoint_list_folder'
            && is_array($result)
        ) {
            $query = (string) ($result['query'] ?? '');
            $folder = (string) ($result['folder'] ?? $query);
            $items = $result['items'] ?? [];

            if (($result['success'] ?? false) !== true) {
                $message = (string) (
                    $result['message']
                    ?? 'Folder SharePoint tidak dapat ditemukan.'
                );

                Log::info(
                    'AI Agent returned SharePoint folder result directly',
                    [
                        'tool' => $toolName,
                        'query' => $query,
                        'success' => false,
                    ]
                );

                return $message;
            }

            $response = "Isi folder SharePoint berhasil dibaca.\n\n";
            $response .= "Folder: {$folder}\n";
            $response .= 'Jumlah item: '
                . (int) ($result['count'] ?? count($items))
                . "\n\n";

            if ($items === []) {
                $response .= 'Folder ini tidak memiliki file atau subfolder.';
            } else {
                foreach ($items as $index => $item) {
                    $number = $index + 1;
                    $name = (string) (
                        $item['name'] ?? 'Nama tidak tersedia'
                    );
                    $type = strtolower(
                        (string) ($item['type'] ?? 'file')
                    );

                    $response .= "{$number}. {$name}\n";
                    $response .= '   Tipe: '
                        . ($type === 'folder' ? 'Folder' : 'File')
                        . "\n";

                    if (
                        isset($item['size'])
                        && is_numeric($item['size'])
                    ) {
                        $response .= '   Ukuran: '
                            . number_format(
                                (float) $item['size'],
                                0,
                                ',',
                                '.'
                            )
                            . " bytes\n";
                    }

                    $modified = $item['lastModifiedDateTime'] ?? null;

                    if (
                        is_string($modified)
                        && $modified !== ''
                    ) {
                        $timestamp = strtotime($modified);

                        $response .= '   Terakhir diubah: '
                            . (
                                $timestamp !== false
                                    ? date('d-m-Y H:i', $timestamp)
                                    : $modified
                            )
                            . "\n";
                    }

                    $url = $item['webUrl'] ?? null;

                    if (is_string($url) && $url !== '') {
                        $response .= "   Tautan: {$url}\n";
                    }

                    $response .= "\n";
                }
            }

            if (($result['truncated'] ?? false) === true) {
                $response .= "\nCatatan: daftar item dibatasi "
                    . 'oleh batas maksimum hasil Folder Reader.';
            }

            Log::info(
                'AI Agent returned SharePoint folder result directly',
                [
                    'tool' => $toolName,
                    'query' => $query,
                    'folder' => $folder,
                    'result_count' => count($items),
                    'success' => true,
                ]
            );

            return trim($response);
        }

        $toolResult = json_encode(
            $result,
            JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        // Pertahankan format JSON mentah untuk pengujian mock_tool.
        if ($toolName === 'mock_tool' && is_array($result)) {
            return $toolResult;
        }

        $finalResponse = $this->provider->chat(
            $systemPrompt
                . "\n\nYou have already executed the requested tool. "
                . "Do not request another tool in this response. "
                . "Explain the actual result to the user.",
            $messages,
            "Tool execution result:\n"
                . "Tool: {$toolName}\n"
                . "Result: {$toolResult}"
        );

        Log::info('AI Agent completed with tool', [
            'tool' => $toolName,
            'duration_ms' => (int) round(
                (microtime(true) - $agentStartedAt) * 1000
            ),
            'history_messages' => $messages->count(),
        ]);

        return $finalResponse;
    }

    private function buildSystemPrompt(): string
    {
        $tools = $this->toolRegistry->all();

        if ($tools === []) {
            return $this->instructions->get();
        }

        $descriptions = [];

        foreach ($tools as $tool) {
            $descriptions[] = sprintf(
                '- Name: %s | Description: %s',
                $tool->name(),
                $tool->description()
            );
        }

        return $this->instructions->get()
            . "\n\nAvailable tools:\n"
            . implode("\n", $descriptions)
            . "\n\nTool usage protocol:"
            . "\nIf a tool is needed, respond ONLY with valid JSON:"
            . "\n{\"type\":\"tool_call\",\"tool\":\"tool_name\","
            . "\"arguments\":{}}"
            . "\nUse the exact registered tool name."
            . "\nIf no tool is needed, respond normally in plain text."
            . "\nNever claim a tool was executed unless its result "
            . "has been provided to you.";
    }

    private function parseToolDecision(
        string $response
    ): ?array {
        $response = trim($response);

        $response = preg_replace(
            '/^```(?:json)?\s*|\s*```$/i',
            '',
            $response
        ) ?? $response;

        try {
            $data = json_decode(
                $response,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            return null;
        }

        if (
            ! is_array($data)
            || ($data['type'] ?? null) !== 'tool_call'
            || ! is_string($data['tool'] ?? null)
            || ! is_array($data['arguments'] ?? null)
        ) {
            return null;
        }

        if ($this->toolRegistry->get($data['tool']) === null) {
            return null;
        }

        return [
            'tool' => $data['tool'],
            'arguments' => $data['arguments'],
        ];
    }
}
