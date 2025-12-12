<?php

namespace App\Http\Controllers\Api;

use App\Models\ProductDeclaration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductDeclarationController extends BaseApiController
{
    /**
     * Get the active product declaration for the current store
     */
    public function show(Request $request): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $declaration = ProductDeclaration::getActiveForStore($store->id);

        if (!$declaration) {
            return response()->json(['message' => 'No active product declaration found'], 404);
        }

        return response()->json([
            'data' => [
                'id' => $declaration->id,
                'product_name' => $declaration->product_name,
                'vendor_name' => $declaration->vendor_name,
                'version' => $declaration->version,
                'version_identification' => $declaration->version_identification,
                'declaration_date' => $declaration->declaration_date?->format('Y-m-d'),
                'content' => $declaration->content,
                'created_at' => $this->formatDateTimeOslo($declaration->created_at),
                'updated_at' => $this->formatDateTimeOslo($declaration->updated_at),
            ],
        ]);
    }

    /**
     * Display the product declaration as an HTML page (for embedding in POS)
     */
    public function display(Request $request)
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            abort(404, 'Store not found');
        }

        $this->authorizeTenant($request, $store);

        $declaration = ProductDeclaration::getActiveForStore($store->id);

        if (!$declaration) {
            abort(404, 'No active product declaration found');
        }

        // Convert markdown to HTML
        $content = $declaration->content;
        
        // Use a simple markdown parser (you might want to use a proper library like Parsedown)
        // For now, we'll use basic markdown conversion
        $htmlContent = $this->markdownToHtml($content);

        return view('product-declaration', [
            'declaration' => $declaration,
            'content' => $htmlContent,
        ]);
    }

    /**
     * Simple markdown to HTML converter
     * For production, consider using a proper library like Parsedown
     */
    protected function markdownToHtml(string $markdown): string
    {
        // Basic markdown conversion
        $html = $markdown;
        
        // Headers
        $html = preg_replace('/^### (.*)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^## (.*)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^# (.*)$/m', '<h1>$1</h1>', $html);
        
        // Bold
        $html = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $html);
        
        // Italic
        $html = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $html);
        
        // Lists
        $html = preg_replace('/^\- (.*)$/m', '<li>$1</li>', $html);
        $html = preg_replace('/^(\d+)\. (.*)$/m', '<li>$2</li>', $html);
        
        // Wrap consecutive list items in ul/ol
        $html = preg_replace('/(<li>.*<\/li>\n?)+/m', '<ul>$0</ul>', $html);
        
        // Paragraphs (lines that aren't headers or list items)
        $lines = explode("\n", $html);
        $result = [];
        $inList = false;
        
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed)) {
                $result[] = '';
                continue;
            }
            
            if (strpos($trimmed, '<h') === 0 || strpos($trimmed, '<ul') === 0 || strpos($trimmed, '</ul') === 0) {
                $result[] = $line;
            } elseif (strpos($trimmed, '<li') === 0) {
                $result[] = $line;
            } else {
                $result[] = '<p>' . $trimmed . '</p>';
            }
        }
        
        $html = implode("\n", $result);
        
        // Code blocks
        $html = preg_replace('/```(\w+)?\n(.*?)```/s', '<pre><code>$2</code></pre>', $html);
        
        // Inline code
        $html = preg_replace('/`(.*?)`/', '<code>$1</code>', $html);
        
        // Links
        $html = preg_replace('/\[([^\]]+)\]\(([^\)]+)\)/', '<a href="$2">$1</a>', $html);
        
        // Horizontal rules
        $html = preg_replace('/^---$/m', '<hr>', $html);
        
        return $html;
    }
}
