
<?php

use App\Services\AI\ToolAccess;
use App\Services\AI\ToolExecutor;
use App\Services\AI\ToolRegistry;
use App\Services\AI\Tools\MockTool;
use RuntimeException;

beforeEach(function () {
    $this->registry = new ToolRegistry();
    $this->registry->register(new MockTool());

    $this->access = new ToolAccess();

    $this->executor = new ToolExecutor(
        $this->registry,
        $this->access
    );
});

test('registered and allowed tool executes successfully', function () {
    $this->access->allow('mock_tool');

    $result = $this->executor->execute('mock_tool', [
        'message' => 'Hello Virtual Office',
    ]);

    expect($result)->toBe([
        'success' => true,
        'tool' => 'mock_tool',
        'message' => 'Hello Virtual Office',
    ]);
});

test('registered but unauthorized tool is rejected', function () {
    expect(fn () => $this->executor->execute('mock_tool', [
        'message' => 'This should be rejected',
    ]))->toThrow(RuntimeException::class, 'Access denied');
});

test('unregistered tool is rejected', function () {
    expect(fn () => $this->executor->execute('unknown_tool', []))
        ->toThrow(RuntimeException::class, 'is not registered');
});
