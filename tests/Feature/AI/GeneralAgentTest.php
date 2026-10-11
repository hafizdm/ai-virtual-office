<?php

use App\Services\AI\Agents\GeneralAgent;
use App\Services\AI\Instructions\GeneralAgentInstructions;
use App\Services\AI\Providers\AIProvider;
use App\Services\AI\Providers\MockAIProvider;
use App\Services\AI\ToolAccess;
use App\Services\AI\ToolExecutor;
use App\Services\AI\ToolRegistry;
use App\Services\AI\Tools\MockTool;
use Illuminate\Support\Collection;
use RuntimeException;

beforeEach(function () {
    $registry = new ToolRegistry();
    $registry->register(new MockTool());

    $access = new ToolAccess();

    $executor = new ToolExecutor($registry, $access);

    $this->agent = new GeneralAgent(
        new MockAIProvider(),
        new GeneralAgentInstructions(),
        $executor
    );

    $this->access = $access;
});

test('general agent can execute an authorized tool', function () {
    $this->access->allow('mock_tool');

    $result = $this->agent->executeTool('mock_tool', [
        'message' => 'Hello Virtual Office',
    ]);

    expect($result)->toBe([
        'success' => true,
        'tool' => 'mock_tool',
        'message' => 'Hello Virtual Office',
    ]);
});

test('general agent cannot execute a tool without permission', function () {
    expect(fn () => $this->agent->executeTool('mock_tool', [
        'message' => 'This should be rejected',
    ]))->toThrow(RuntimeException::class, 'Access denied');
});

test('general agent can still respond to normal chat', function () {
    $response = $this->agent->run(
        new Collection(),
        'Halo, siapa kamu?'
    );

    expect($response)
        ->toContain('System instruction')
        ->toContain('conversation history');
});


test('general agent executes a simulated tool call', function () {
    $this->access->allow('mock_tool');

    $result = $this->agent->runToolSimulation(
        'mock_tool',
        ['message' => 'Hello from simulated tool call']
    );

    expect($result)->toBe([
        'success' => true,
        'tool' => 'mock_tool',
        'message' => 'Hello from simulated tool call',
    ]);
});

test('general agent rejects a simulated tool call without permission', function () {
    expect(fn () => $this->agent->runToolSimulation(
        'mock_tool',
        ['message' => 'This must be rejected']
    ))->toThrow(
        RuntimeException::class,
        'Access denied'
    );
});


test('general agent executes a detected tool command', function () {
    $this->access->allow('mock_tool');

    $result = $this->agent->runWithToolDetection(
        new Collection(),
        'Jalankan mock tool dengan pesan Hello Virtual Office'
    );

    expect(json_decode($result, true))->toBe([
        'success' => true,
        'tool' => 'mock_tool',
        'message' => 'Hello Virtual Office',
    ]);
});

test('general agent rejects a detected tool command without permission', function () {
    expect(fn () => $this->agent->runWithToolDetection(
        new Collection(),
        'Jalankan mock tool dengan pesan Hello Virtual Office'
    ))->toThrow(RuntimeException::class, 'Access denied');
});


