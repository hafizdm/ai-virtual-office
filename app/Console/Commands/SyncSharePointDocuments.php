<?php

namespace App\Console\Commands;

use App\Services\SharePointDocumentSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('app:sync-share-point-documents')]
#[Description('Synchronize document metadata from the HR-GARII SharePoint Documents library.')]
class SyncSharePointDocuments extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(
        SharePointDocumentSyncService $syncService
    ): int {
        $this->info('Starting SharePoint document synchronization...');

        try {
            $result = $syncService->sync();

            $this->newLine();
            $this->info('SharePoint synchronization completed.');

            $this->table(
                ['Metric', 'Value'],
                [
                    ['Site', $result['site']],
                    ['Library', $result['library']],
                    ['Items scanned', $result['scannedItems']],
                    ['Files processed', $result['filesProcessed']],
                    ['New documents', $result['created']],
                    ['Updated documents', $result['updated']],
                ]
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);

            $this->newLine();
            $this->error('SharePoint synchronization failed.');
            $this->line($exception->getMessage());

            return self::FAILURE;
        }
    }
}
