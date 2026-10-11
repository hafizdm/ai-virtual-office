<?php

namespace App\Services\AI;

use App\Services\AI\Tools\Tool;

class ToolAccess
{
    /**
     * @var array<string>
     */
    private array $allowedTools = [];

    public function allow(string $toolName): void
    {
        if (! in_array($toolName, $this->allowedTools, true)) {
            $this->allowedTools[] = $toolName;
        }
    }

    public function deny(string $toolName): void
    {
        $this->allowedTools = array_values(
            array_filter(
                $this->allowedTools,
                fn (string $allowedTool) => $allowedTool !== $toolName
            )
        );
    }

    public function canUse(Tool $tool): bool
    {
        return in_array(
            $tool->name(),
            $this->allowedTools,
            true
        );
    }
}