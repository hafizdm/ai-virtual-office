<?php

namespace App\Console\Commands;

use App\Services\MicrosoftGraphService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use RuntimeException;
use Throwable;

class CheckSharePointConnection extends Command
{
    protected $signature = 'sharepoint:check';

    protected $description = 'Check Microsoft Graph connection to HR-GARII';

    public function handle(MicrosoftGraphService $graph): int
    {
        $this->info('Checking Microsoft Graph connection...');

        try {
            $site = $graph->getHrGariiSite();

            $this->newLine();
            $this->info('Microsoft Graph connection successful.');
            $this->line('Site name: ' . ($site['displayName'] ?? 'Unknown'));
            $this->line('Site ID: ' . ($site['id'] ?? 'Not returned'));
            $this->line('Web URL: ' . ($site['webUrl'] ?? 'Not returned'));

            return self::SUCCESS;
        } catch (RequestException $e) {
            $status = $e->response->status();

            $this->error("Microsoft Graph returned HTTP {$status}.");

            if ($status === 403) {
                $this->warn(
                    'Check Sites.Selected permission, admin consent, '
                    . 'and the explicit permission grant for HR-GARII.'
                );
            } elseif ($status === 401) {
                $this->warn(
                    'Check the tenant ID, client ID, client secret, '
                    . 'and token configuration.'
                );
            } else {
                $this->line($e->response->body());
            }

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
