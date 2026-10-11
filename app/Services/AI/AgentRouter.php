<?php

namespace App\Services\AI;

use App\Services\AI\Agents\GeneralAgent;
use Illuminate\Support\Collection;

class AgentRouter
{
    public function __construct(
        private GeneralAgent $generalAgent
    ) {
    }

    public function route(
        Collection $messages,
        string $message
    ): string {
        return $this->generalAgent->run(
            $messages,
            $message
        );
    }
}
