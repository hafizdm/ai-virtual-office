<?php

namespace App\Jobs;

use App\Models\CvBatch;
use App\Models\CvBatchItem;
use App\Services\AI\CV\CvTenderProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProcessCvBatchItem implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 1;

    public function __construct(public int $itemId)
    {
    }

    public function handle(CvTenderProcessor $processor): void
    {
        $item = CvBatchItem::query()->findOrFail($this->itemId);

        // Idempotency guard: a completed item must not be generated twice.
        if ($item->status === 'success') {
            self::refreshBatchProgress((int) $item->cv_batch_id);
            return;
        }

        $batch = CvBatch::query()->findOrFail($item->cv_batch_id);
        $templateRelativePath = (string) $batch->template_path;

        if ($templateRelativePath === '' || ! Storage::disk('local')->exists($templateRelativePath)) {
            $this->markFailed($item, 'Template CV tidak ditemukan.');
            return;
        }

        $item->update([
            'status' => 'processing',
            'started_at' => now(),
            'finished_at' => null,
            'error_message' => null,
        ]);

        try {
            if (empty($item->drive_id) || empty($item->source_item_id)) {
                throw new \RuntimeException('Drive ID atau item ID SharePoint tidak tersedia.');
            }

            $candidateName = pathinfo((string) $item->source_filename, PATHINFO_FILENAME);
            $candidateName = preg_replace('/^CV[\s_-]*/i', '', $candidateName) ?: $candidateName;
            $outputFilename = 'CV-' . Str::slug($candidateName) . '.docx';
            $outputDirectory = 'cv-outputs/batch-' . $batch->id;
            $relativeOutputPath = $outputDirectory . '/' . $outputFilename;
            Storage::disk('local')->makeDirectory($outputDirectory);

            $result = $processor->process(
                $item->drive_id,
                $item->source_item_id,
                Storage::disk('local')->path($templateRelativePath),
                Storage::disk('local')->path($relativeOutputPath),
                (string) $item->source_filename
            );

            if (!($result['success'] ?? false)) {
                throw new \RuntimeException($result['message'] ?? 'CV gagal diproses.');
            }

            $item->update([
                'status' => 'success',
                'output_path' => $relativeOutputPath,
                'output_filename' => $outputFilename,
                'validation_warnings' => [
                    'missing_fields' => $result['missing_fields'] ?? [],
                    'extraction_status' => $result['extraction_status'] ?? 'needs_review',
                ],
                'error_message' => null,
                'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $this->markFailed($item, $exception->getMessage());
            return;
        }

        self::refreshBatchProgress((int) $batch->id);
    }

    public function failed(?Throwable $exception): void
    {
        $item = CvBatchItem::query()->find($this->itemId);
        if (!$item || $item->status === 'success') {
            return;
        }

        $this->markFailed(
            $item,
            $exception?->getMessage() ?? 'Job gagal setelah melewati batas percobaan.'
        );
    }

    private function markFailed(CvBatchItem $item, string $message): void
    {
        $item->update([
            'status' => 'failed',
            'error_message' => Str::limit($message, 5000),
            'finished_at' => now(),
        ]);

        self::refreshBatchProgress((int) $item->cv_batch_id);
    }

    private static function refreshBatchProgress(int $batchId): void
    {
        $batch = CvBatch::query()->find($batchId);
        if (!$batch) {
            return;
        }

        $items = $batch->items();
        $successful = (clone $items)->where('status', 'success')->count();
        $failed = (clone $items)->where('status', 'failed')->count();
        $total = (clone $items)->count();
        $processed = $successful + $failed;

        $updates = [
            'total_files' => $total,
            'processed_files' => $processed,
            'successful_files' => $successful,
            'failed_files' => $failed,
        ];

        if ($total > 0 && $processed >= $total) {
            $updates['status'] = $failed === 0
                ? 'completed'
                : ($successful > 0 ? 'completed_with_errors' : 'failed');
            $updates['finished_at'] = now();
        } else {
            $updates['status'] = 'processing';
        }

        $batch->update($updates);
    }
}
