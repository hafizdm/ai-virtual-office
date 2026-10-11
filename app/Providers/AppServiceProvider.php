<?php

namespace App\Providers;

use App\Services\AI\Providers\AIProvider;
use App\Services\AI\Providers\GeminiAIProvider;
use App\Services\AI\Providers\MockAIProvider;
use App\Services\AI\ToolAccess;
use App\Services\AI\ToolRegistry;
use App\Services\AI\Tools\MockTool;
use App\Services\AI\Tools\SharePointSearchTool;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            AIProvider::class,
            function ($app) {
                $driver = config('services.ai.driver', 'mock');

                return match ($driver) {
                    'mock' => $app->make(MockAIProvider::class),
                    'gemini' => $app->make(GeminiAIProvider::class),
                    default => throw new InvalidArgumentException(
                        "Unsupported AI driver [{$driver}]."
                    ),
                };
            }
        );

        $this->app->singleton(
            ToolRegistry::class,
            function ($app) {
                $registry = new ToolRegistry();

                $registry->register(
                    $app->make(MockTool::class)
                );

                $registry->register(
                    $app->make(SharePointSearchTool::class)
                );

                return $registry;
            }
        );

        $this->app->singleton(
            ToolAccess::class,
            function () {
                $access = new ToolAccess();

                $access->allow('mock_tool');
                $access->allow('sharepoint_search');

                return $access;
            }
        );
    }

    public function boot(): void
    {
        //
    }
}
