<?php

namespace App\Services\AI\Tools;

interface Tool
{
    public function name(): string;

    public function description(): string;

    public function execute(array $arguments): mixed;
}