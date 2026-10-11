<?php

namespace App\Services\AI\Tools;

class MockTool implements Tool
{
    public function name(): string
    {
        return 'mock_tool';
    }

    public function description(): string
    {
        return 'A development tool used to test the AI Virtual Office tool architecture.';
    }

    public function execute(array $arguments): mixed
    {
        $message = $arguments['message'] ?? 'No message provided.';

        return [
            'success' => true,
            'tool' => $this->name(),
            'message' => $message,
        ];
    }
}