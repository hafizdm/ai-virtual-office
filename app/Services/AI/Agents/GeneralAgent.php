<?php

namespace App\Services\AI\Agents;

use App\Services\AI\Instructions\GeneralAgentInstructions;
use App\Services\AI\Providers\AIProvider;
use App\Services\AI\ToolExecutor;
use App\Services\AI\ToolRegistry;
use Illuminate\Support\Collection;
use JsonException;

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
        $systemPrompt = $this->buildSystemPrompt();

        $response = $this->provider->chat(
            $systemPrompt,
            $messages,
            $message
        );

        $decision = $this->parseToolDecision($response);

        // Jika AI tidak meminta tool, pertahankan
        // perilaku chat yang sudah berjalan.
        if ($decision === null) {
            return $response;
        }

        $toolName = $decision['tool'];
        $arguments = $decision['arguments'];

        // ToolExecutor memeriksa registrasi dan izin akses.
        $result = $this->toolExecutor->execute(
            $toolName,
            $arguments
        );

        $toolResult = json_encode(
            $result,
            JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        // Kirim hasil tool kembali ke AI agar dapat
        // menyusun jawaban untuk pengguna.
        return $this->provider->chat(
            $systemPrompt
                . "\n\nYou have already executed the requested tool. "
                . "Do not request another tool in this response. "
                . "Explain the actual result to the user.",
            $messages,
            "Tool execution result:\n"
                . "Tool: {$toolName}\n"
                . "Result: {$toolResult}"
        );
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

        // Mendukung JSON yang dibungkus code fence.
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

        // Jangan menjalankan nama tool yang tidak terdaftar.
        if ($this->toolRegistry->get($data['tool']) === null) {
            return null;
        }

        return [
            'tool' => $data['tool'],
            'arguments' => $data['arguments'],
        ];
    }
}
