<?php

namespace App\Services\AI\Providers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class OllamaAIProvider implements AIProvider
{
    public function chat(
        string $systemPrompt,
        Collection $messages,
        string $message
    ): string {
        $baseUrl = rtrim(
            config('services.ollama.base_url', 'http://localhost:11434'),
            '/'
        );

        $model = config('services.ollama.model', 'qwen3:8b');

        $conversation = [];

        if (trim($systemPrompt) !== '') {
            $conversation[] = [
                'role' => 'system',
                'content' => $systemPrompt,
            ];
        }

        foreach ($messages as $historyMessage) {
            $role = $historyMessage->role === 'assistant'
                ? 'assistant'
                : 'user';

            $conversation[] = [
                'role' => $role,
                'content' => $historyMessage->content,
            ];
        }

        $conversation[] = [
            'role' => 'user',
            'content' => $message,
        ];

        $historyContentChars = 0;
        $payloadContentChars = 0;

        foreach ($conversation as $item) {
            $contentChars = mb_strlen(
                (string) ($item['content'] ?? '')
            );

            $payloadContentChars += $contentChars;

            if (($item['role'] ?? '') !== 'system') {
                $historyContentChars += $contentChars;
            }
        }

        $startedAt = microtime(true);

        try {

            Log::info('AI Ollama request started', [
                'model' => $model,
                'history_messages' => count($conversation),
                'request_message_chars' => strlen(
                    (string) ($conversation[count($conversation) - 1]['content'] ?? '')
                ),
            ]);
            
            $response = Http::timeout(90)
            ->acceptJson()
            ->post($baseUrl . '/api/chat', [
                'model' => $model,
                'messages' => $conversation,
                'stream' => false,
                'think' => false,
                'options' => [
                    'num_predict' => 256,
                ],
            ]);

            $durationMs = (int) round(
                (microtime(true) - $startedAt) * 1000
            );

            $data = $response->json();

            if (! is_array($data)) {
                $data = [];
            }

            $evalCount = $data['eval_count'] ?? null;
            $evalDuration = $data['eval_duration'] ?? null;

            $outputTokensPerSecond = null;

            if (
                is_numeric($evalCount)
                && is_numeric($evalDuration)
                && (float) $evalDuration > 0
            ) {
                $outputTokensPerSecond = round(
                    (float) $evalCount
                    / ((float) $evalDuration / 1_000_000_000),
                    2
                );
            }

            Log::info('AI Ollama request completed', [
                'model' => $model,
                'duration_ms' => $durationMs,
                'http_status' => $response->status(),

                'history_messages' => $messages->count(),
                'conversation_entries' => count($conversation),

                'system_prompt_chars' => mb_strlen($systemPrompt),
                'request_message_chars' => mb_strlen($message),
                'history_content_chars' => $historyContentChars,
                'payload_content_chars' => $payloadContentChars,

                'ollama_total_duration_ms' =>
                    isset($data['total_duration'])
                        ? round($data['total_duration'] / 1_000_000, 2)
                        : null,

                'ollama_load_duration_ms' =>
                    isset($data['load_duration'])
                        ? round($data['load_duration'] / 1_000_000, 2)
                        : null,

                'prompt_eval_count' =>
                    $data['prompt_eval_count'] ?? null,

                'prompt_eval_duration_ms' =>
                    isset($data['prompt_eval_duration'])
                        ? round(
                            $data['prompt_eval_duration']
                            / 1_000_000,
                            2
                        )
                        : null,

                'eval_count' => $evalCount,

                'eval_duration_ms' =>
                    is_numeric($evalDuration)
                        ? round(
                            (float) $evalDuration / 1_000_000,
                            2
                        )
                        : null,

                'output_tokens_per_second' =>
                    $outputTokensPerSecond,
            ]);
        } catch (Throwable $exception) {
            Log::error('AI Ollama request failed', [
                'model' => $model,
                'duration_ms' => (int) round(
                    (microtime(true) - $startedAt) * 1000
                ),
                'history_messages' => $messages->count(),
                'conversation_entries' => count($conversation),
                'system_prompt_chars' => mb_strlen($systemPrompt),
                'request_message_chars' => mb_strlen($message),
                'payload_content_chars' => $payloadContentChars,
                'exception' => get_class($exception),
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                'Ollama API request failed: HTTP '
                . $response->status() . '.'
            );
        }

        $text = $data['message']['content'] ?? null;

        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException(
                'Ollama API returned an empty response.'
            );
        }

        return trim($text);
    }
}
