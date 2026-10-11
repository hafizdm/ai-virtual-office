<?php

namespace App\Services\AI;

use App\Services\AI\Tools\Tool;

class ToolRegistry
{
    /**
     * @var array<string, Tool>
     */
    private array $tools = [];

    public function register(Tool $tool): void
    {
        $this->tools[$tool->name()] = $tool;
    }

    public function get(string $name): ?Tool
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * @return array<string, Tool>
     */
    public function all(): array
    {
        return $this->tools;
    }
}