<?php

namespace App\Console\Commands;

use App\Models\ReceiptTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SeedReceiptTemplates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'receipt-templates:seed 
                            {--force : Force update existing templates}
                            {--store= : Store ID to seed templates for (default: global)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed receipt templates from files to database';

    protected $templatePath;
    protected $templates = [
        'sales' => 'sales-receipt.xml',
        'return' => 'return-receipt.xml',
        'copy' => 'copy-receipt.xml',
        'steb' => 'steb-receipt.xml',
        'provisional' => 'provisional-receipt.xml',
        'training' => 'training-receipt.xml',
        'delivery' => 'delivery-receipt.xml',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->templatePath = base_path('resources/receipt-templates/epson');
        $storeId = $this->option('store') ? (int) $this->option('store') : null;
        $force = $this->option('force');

        if (!File::isDirectory($this->templatePath)) {
            $this->error("Template directory not found: {$this->templatePath}");
            return Command::FAILURE;
        }

        $this->info('Seeding receipt templates from files...');
        if ($storeId) {
            $this->info("Store ID: {$storeId}");
        } else {
            $this->info("Global templates (no store)");
        }

        $seeded = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($this->templates as $type => $filename) {
            $filePath = $this->templatePath . '/' . $filename;

            if (!File::exists($filePath)) {
                $this->warn("Template file not found: {$filename}");
                $skipped++;
                continue;
            }

            $content = File::get($filePath);
            $existing = ReceiptTemplate::where('store_id', $storeId)
                ->where('template_type', $type)
                ->first();

            if ($existing) {
                if ($force || !$existing->is_custom) {
                    // Only update if forced or if it's not a custom template
                    $existing->update([
                        'content' => $content,
                        'is_custom' => false,
                        'version' => '1.0',
                        'updated_by' => auth()->id(),
                    ]);
                    $this->info("✓ Updated: {$type}");
                    $updated++;
                } else {
                    $this->warn("⊘ Skipped (custom): {$type}");
                    $skipped++;
                }
            } else {
                ReceiptTemplate::create([
                    'store_id' => $storeId,
                    'template_type' => $type,
                    'content' => $content,
                    'is_custom' => false,
                    'version' => '1.0',
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);
                $this->info("✓ Seeded: {$type}");
                $seeded++;
            }
        }

        $this->newLine();
        $this->info("Summary:");
        $this->info("  Seeded: {$seeded}");
        $this->info("  Updated: {$updated}");
        $this->info("  Skipped: {$skipped}");

        return Command::SUCCESS;
    }
}
