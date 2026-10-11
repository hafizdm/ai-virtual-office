<?php

namespace App\Services\AI\Responses;

final class AgentResponse
{
    private function __construct(
        public readonly string $type,
        public readonly ?string $text = null,
        public readonly ?string $toolName = null,
        public readonly array $arguments = [],
    ) {
    }

    public static function text(string $text): self
    {
        return new self(
            type: 'text',
            text: $text,
        );
    }

    public static function toolCall(
        string $toolName,
        array $arguments = [],
    ): self {
        return new self(
            type: 'tool_call',
            toolName: $toolName,
            arguments: $arguments,
        );
    }

    public function isText(): bool
    {
        return $this->type === 'text';
    }

    public function isToolCall(): bool
    {
        return $this->type === 'tool_call';
    }
}
