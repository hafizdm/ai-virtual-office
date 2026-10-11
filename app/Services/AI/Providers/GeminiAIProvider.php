<?php

namespace App\Services\AI\Providers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiAIProvider implements AIProvider
{
    private const MAX_ATTEMPTS = 3;

    public function chat(
        string $systemPrompt,
        Collection $messages,
        string $message
    ): string {
        $apiKey = config('services.gemini.key');
        $model = config('services.gemini.model');
        $baseUrl = rtrim(config('services.gemini.base_url'), '/');

        if (! $apiKey) {
            throw new RuntimeException('Gemini API key is not configured.');
        }

        if (! $model) {
            throw new RuntimeException('Gemini model is not configured.');
        }

        $contents = [];

        foreach ($messages as $historyMessage) {
            $role = $historyMessage->role === 'assistant'
                ? 'model'
                : 'user';

            $contents[] = [
                'role' => $role,
                'parts' => [
                    ['text' => $historyMessage->content],
                ],
            ];
        }

        $contents[] = [
            'role' => 'user',
            'parts' => [
                ['text' => $message],
            ],
        ];

        $url = "{$baseUrl}/v1beta/models/{$model}:generateContent";

        $payload = [
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemPrompt],
                ],
            ],
            'contents' => $contents,
        ];

        $lastResponse = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $response = Http::timeout(60)
                    ->acceptJson()
                    ->withQueryParameters(['key' => $apiKey])
                    ->post($url, $payload);

                $lastResponse = $response;

                if ($response->successful()) {
                    $text = $response->json(
                        'candidates.0.content.parts.0.text'
                    );

                    if (! is_string($text) || trim($text) === '') {
                        throw new RuntimeException(
                            'Gemini API returned an empty response.'
                        );
                    }

                    return trim($text);
                }

                if ($response->status() !== 503) {
                    throw new RuntimeException(
                        'Gemini API request failed: HTTP '
                        . $response->status()
                        . '.'
                    );
                }

                if ($attempt < self::MAX_ATTEMPTS) {
                    sleep($attempt);
                }
            } catch (ConnectionException $exception) {
                throw new RuntimeException(
                    'Unable to connect to Gemini API.',
                    previous: $exception
                );
            }
        }

        throw new RuntimeException(
            'Gemini API is temporarily unavailable after '
            . self::MAX_ATTEMPTS
            . ' attempts. Please try again later.'
        );
    }
}