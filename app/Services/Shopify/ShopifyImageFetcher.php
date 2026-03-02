<?php

namespace App\Services\Shopify;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ShopifyImageFetcher
{
    public function __construct(
        protected ?string $disk = null,
    ) {}

    public function disk(): string
    {
        return $this->disk ?: (string) (env('SHOPIFY_IMPORT_IMAGES_DISK', 'public'));
    }

    public function maxBytes(): int
    {
        return (int) (env('SHOPIFY_IMPORT_IMAGE_MAX_BYTES', 10_000_000)); // 10MB default
    }

    public function allowedHosts(): array
    {
        $raw = trim((string) env('SHOPIFY_IMPORT_IMAGE_HOST_ALLOWLIST', ''));
        if ($raw === '') return [];
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    /**
     * Downloads image URL, validates it is a real image (bytes), stores to disk, returns hosted URL.
     *
     * @return array{ok:bool,url?:string,path?:string,reason?:string,bytes?:int,mime?:string}
     */
    public function fetchAndStore(string $runId, string $handle, string $sourceUrl): array
    {
        $sourceUrl = trim($sourceUrl);

        // Basic URL sanity
        $u = parse_url($sourceUrl);
        $scheme = strtolower((string)($u['scheme'] ?? ''));
        $host   = strtolower((string)($u['host'] ?? ''));

        if (! in_array($scheme, ['http','https'], true) || $host === '') {
            return ['ok' => false, 'reason' => 'invalid_url'];
        }

        $allow = $this->allowedHosts();
        if (! empty($allow) && ! in_array($host, $allow, true)) {
            return ['ok' => false, 'reason' => 'host_not_allowed'];
        }

        $tmpDir  = storage_path('app/tmp/shopify-import/img-tmp');
        if (! is_dir($tmpDir)) @mkdir($tmpDir, 0775, true);

        $tmpFile = $tmpDir . '/' . sha1($sourceUrl . microtime(true)) . '.bin';

        try {
            $resp = Http::retry(2, 250)
                ->timeout(25)
                ->withHeaders([
                    'User-Agent' => 'ShopifyImport/1.0 (+https://example.local)',
                    'Accept'     => 'image/*,*/*;q=0.8',
                ])
                ->sink($tmpFile)
                ->get($sourceUrl);

            if (! $resp->ok()) {
                @unlink($tmpFile);
                return ['ok' => false, 'reason' => 'http_' . $resp->status()];
            }

            $bytes = @filesize($tmpFile) ?: 0;
            if ($bytes <= 0) {
                @unlink($tmpFile);
                return ['ok' => false, 'reason' => 'empty'];
            }

            if ($bytes > $this->maxBytes()) {
                @unlink($tmpFile);
                return ['ok' => false, 'reason' => 'too_large', 'bytes' => $bytes];
            }

            // Validate bytes are an actual image
            $imgType = @exif_imagetype($tmpFile);
            if (! $imgType) {
                @unlink($tmpFile);
                return ['ok' => false, 'reason' => 'not_an_image'];
            }

            $mime = @image_type_to_mime_type($imgType) ?: null;

            // Determine extension from actual bytes
            $ext = match ($imgType) {
                IMAGETYPE_JPEG => 'jpg',
                IMAGETYPE_PNG  => 'png',
                IMAGETYPE_GIF  => 'gif',
                IMAGETYPE_WEBP => 'webp',
                default        => 'img',
            };

            // Stable-ish filename: content hash
            $hash = @sha1_file($tmpFile) ?: sha1($sourceUrl);
            $safeHandle = preg_replace('/[^a-zA-Z0-9\-_]/', '-', $handle) ?: 'product';

            $destPath = "shopify-import/images/{$runId}/{$safeHandle}/{$hash}.{$ext}";

            $disk = $this->disk();
            $stream = fopen($tmpFile, 'rb');
            Storage::disk($disk)->put($destPath, $stream, [
                'visibility' => 'public',
                'ContentType' => $mime ?: 'application/octet-stream',
            ]);
            if (is_resource($stream)) fclose($stream);

            @unlink($tmpFile);

            $url = Storage::disk($disk)->url($destPath);

            return [
                'ok'    => true,
                'url'   => $url,
                'path'  => $destPath,
                'bytes' => $bytes,
                'mime'  => $mime,
            ];
        } catch (\Throwable $e) {
            @unlink($tmpFile);
            Log::warning('ShopifyImageFetcher failed', [
                'url'   => $sourceUrl,
                'error' => $e->getMessage(),
            ]);
            return ['ok' => false, 'reason' => 'exception'];
        }
    }
}
