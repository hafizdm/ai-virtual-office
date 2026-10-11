<?php

namespace App\Services\AI;

use RuntimeException;

class ToolExecutor
{
    public function __construct(
        private ToolRegistry $registry,
        private ToolAccess $access
    ) {
    }

    public function execute(
        string $toolName,
        array $arguments = []
    ): mixed {
        $tool = $this->registry->get($toolName);

        if ($tool === null) {
            throw new RuntimeException(
                "Tool [{$toolName}] is not registered."
            );
        }

        if (! $this->access->canUse($tool)) {
            throw new RuntimeException(
                "Access denied for tool [{$toolName}]."
            );
        }

        return $tool->execute($arguments);
    }
}
