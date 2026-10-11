<?php

namespace App\Services\AI;

class ToolCatalog
{
    public function __construct(
        private ToolRegistry $toolRegistry
    ) {
    }

    /**
     * @return array<int, array{
     *     name: string,
     *     description: string
     * }>
     */
    public function all(): array
    {
        return array_values(
            array_map(
                fn ($tool) => [
                    'name' => $tool->name(),
                    'description' => $tool->description(),
                ],
                $this->toolRegistry->all()
            )
        );
    }
}