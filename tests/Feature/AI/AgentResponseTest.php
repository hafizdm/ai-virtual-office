<?php

use App\Services\AI\Responses\AgentResponse;

test('it creates a text response', function () {
    $response = AgentResponse::text('Halo!');

    expect($response->type)->toBe('text')
        ->and($response->text)->toBe('Halo!')
        ->and($response->toolName)->toBeNull()
        ->and($response->arguments)->toBe([])
        ->and($response->isText())->toBeTrue()
        ->and($response->isToolCall())->toBeFalse();
});

test('it creates a tool call response', function () {
    $response = AgentResponse::toolCall('mock_tool', [
        'message' => 'Hello Virtual Office',
    ]);

    expect($response->type)->toBe('tool_call')
        ->and($response->text)->toBeNull()
        ->and($response->toolName)->toBe('mock_tool')
        ->and($response->arguments)->toBe([
            'message' => 'Hello Virtual Office',
        ])
        ->and($response->isText())->toBeFalse()
        ->and($response->isToolCall())->toBeTrue();
});
