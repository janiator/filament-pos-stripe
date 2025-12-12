<?php

namespace App\Filament\Resources\ProductDeclarations\Pages;

use App\Filament\Resources\ProductDeclarations\ProductDeclarationResource;
use App\Models\ProductDeclaration;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\File;

class CreateProductDeclaration extends CreateRecord
{
    protected static string $resource = ProductDeclarationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set store_id if not set (from tenant context)
        if (!isset($data['store_id'])) {
            try {
                $tenant = \Filament\Facades\Filament::getTenant();
                if ($tenant) {
                    $data['store_id'] = $tenant->id;
                }
            } catch (\Throwable $e) {
                // Fallback if Filament facade not available
            }
        }

        // Load previous declaration as default if available
        if (isset($data['store_id'])) {
            $previousDeclaration = ProductDeclaration::where('store_id', $data['store_id'])
                ->orderBy('created_at', 'desc')
                ->first();

            if ($previousDeclaration) {
                // Pre-fill with previous declaration data
                if (empty($data['product_name'])) {
                    $data['product_name'] = $previousDeclaration->product_name;
                }
                if (empty($data['vendor_name'])) {
                    $data['vendor_name'] = $previousDeclaration->vendor_name;
                }
                if (empty($data['version'])) {
                    $data['version'] = $previousDeclaration->version;
                }
                if (empty($data['version_identification'])) {
                    $data['version_identification'] = $previousDeclaration->version_identification;
                }
                if (empty($data['content'])) {
                    $data['content'] = $previousDeclaration->content;
                }
            }
        }

        // Load default content from markdown file if still empty
        if (empty($data['content'])) {
            $defaultContentPath = base_path('docs/compliance/PRODUKTFRASEGN.md');
            if (File::exists($defaultContentPath)) {
                $data['content'] = File::get($defaultContentPath);
            }
        }

        // Deactivate other active declarations for this store
        if (isset($data['store_id']) && ($data['is_active'] ?? false)) {
            ProductDeclaration::where('store_id', $data['store_id'])
                ->where('is_active', true)
                ->update(['is_active' => false]);
        }

        return $data;
    }

    /**
     * Get default form data from previous declaration
     */
    public function mount(): void
    {
        parent::mount();

        try {
            $tenant = \Filament\Facades\Filament::getTenant();
            if ($tenant) {
                $previousDeclaration = ProductDeclaration::where('store_id', $tenant->id)
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($previousDeclaration) {
                    // Pre-fill form with previous declaration data
                    $this->form->fill([
                        'store_id' => $tenant->id,
                        'product_name' => $previousDeclaration->product_name,
                        'vendor_name' => $previousDeclaration->vendor_name,
                        'version' => $previousDeclaration->version,
                        'version_identification' => $previousDeclaration->version_identification,
                        'content' => $previousDeclaration->content,
                        'declaration_date' => now(),
                        'is_active' => true,
                    ]);
                } else {
                    // No previous declaration, load from markdown file
                    $defaultContentPath = base_path('docs/compliance/PRODUKTFRASEGN.md');
                    if (File::exists($defaultContentPath)) {
                        $this->form->fill([
                            'store_id' => $tenant->id,
                            'content' => File::get($defaultContentPath),
                            'declaration_date' => now(),
                            'is_active' => true,
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            // Fallback if Filament facade not available
        }
    }
}
