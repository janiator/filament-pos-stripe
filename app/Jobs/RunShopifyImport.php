<?php

namespace App\Jobs;

use App\Models\ShopifyImportRun;
use App\Services\ShopifyCsvImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunShopifyImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $runId,
        public string $filePath,
        public string $stripeAccountId,
        public bool $downloadImages = false,
    ) {
        //
    }

    public function handle(ShopifyCsvImporter $importer): void
    {
        /** @var ShopifyImportRun|null $run */
        $run = ShopifyImportRun::find($this->runId);
        if (! $run) {
            Log::warning('Shopify import run not found', ['run_id' => $this->runId]);
            return;
        }

        $run->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

        $progressCallback = function (
            int $index,
            int $total,
            array $productData,
            int $imported,
            int $skipped,
            int $errorCount
        ) use ($run) {
            $run->update([
                'current_index' => $index,
                'total_products' => $total,
                'current_title' => $productData['title'] ?? null,
                'current_handle' => $productData['handle'] ?? null,
                'current_category' => $productData['category'] ?? null,
                'imported' => $imported,
                'skipped' => $skipped,
                'error_count' => $errorCount,
            ]);
        };

        try {
            $result = $importer->import(
                $this->filePath,
                $this->stripeAccountId,
                $progressCallback,
                $this->downloadImages,
            );

            $status = 'completed';
            if (($result['error_count'] ?? 0) > 0) {
                $status = 'completed-with-errors';
            }

            $run->update([
                'status' => $status,
                'imported' => $result['imported'] ?? 0,
                'skipped' => $result['skipped'] ?? 0,
                'error_count' => $result['error_count'] ?? 0,
                'total_products' => $result['stats']['import']['total_products']
                    ?? ($result['stats']['parse']['total_products'] ?? $run->total_products),
                'meta' => [
                    'result' => $result,
                ],
                'finished_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'error_count' => $run->error_count + 1,
                'meta' => [
                    'exception' => [
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ],
                ],
                'finished_at' => now(),
            ]);

            Log::error('Shopify import run failed', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
