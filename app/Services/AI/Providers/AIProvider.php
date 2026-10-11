<?php

namespace App\Services\AI\Providers;

use Illuminate\Support\Collection;

interface AIProvider
{
    /**
     * Mengirim system instruction,
     * conversation history,
     * dan pesan terbaru
     * ke AI provider.
     */
    public function chat(
        string $systemPrompt,
        Collection $messages,
        string $message
    ): string;
}