<?php

use App\Services\AI\Providers\MockAIProvider;
use App\Services\AI\Responses\AgentResponse;

test('mock provider can simulate a tool call', function () {
    $provider = new MockAIProvider();

    $response = $provider->simulateToolCall(
        'mock_tool',
        ['message' => 'Hello Virtual Office']
    );

    expect($response)->toBeInstanceOf(AgentResponse::class)
        ->and($response->isToolCall())->toBeTrue()
        ->and($response->toolName)->toBe('mock_tool')
        ->and($response->arguments)->toBe([
            'message' => 'Hello Virtual Office',
        ]);
});


test('mock provider detects an explicit tool command', function () {
    $provider = new MockAIProvider();

    $response = $provider->detectToolCall(
        'Jalankan mock tool dengan pesan Hello Virtual Office'
    );

    expect($response)->toBeInstanceOf(AgentResponse::class)
        ->and($response->isToolCall())->toBeTrue()
        ->and($response->toolName)->toBe('mock_tool')
        ->and($response->arguments)->toBe([
            'message' => 'Hello Virtual Office',
        ]);
});

test('mock provider ignores normal chat messages for tool detection', function () {
    $provider = new MockAIProvider();

    expect($provider->detectToolCall('Halo, siapa kamu?'))
        ->toBeNull();
});

