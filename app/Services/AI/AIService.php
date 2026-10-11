<?php

namespace App\Services\AI;

use Illuminate\Support\Collection;

class AIService
{
    public function __construct(
        private AgentRouter $agentRouter
    ) {
    }

    public function chat(
        Collection $messages,
        string $message
    ): string {
        return $this->agentRouter->route(
            $messages,
            $message
        );
    }
}