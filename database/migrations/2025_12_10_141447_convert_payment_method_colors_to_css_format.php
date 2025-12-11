<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Converts payment method colors from #AARRGGBB format (alpha first) 
     * to #RRGGBBAA format (CSS format with alpha at end)
     */
    public function up(): void
    {
        // Convert background_color from #AARRGGBB to #RRGGBBAA
        DB::table('payment_methods')
            ->whereNotNull('background_color')
            ->where('background_color', 'like', '#________')
            ->get()
            ->each(function ($paymentMethod) {
                $color = $paymentMethod->background_color;
                // Check if it's in old format (#AARRGGBB) - 8 hex digits
                if (preg_match('/^#([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})$/', $color, $matches)) {
                    $alpha = $matches[1];
                    $red = $matches[2];
                    $green = $matches[3];
                    $blue = $matches[4];
                    
                    // Convert to CSS format: #RRGGBBAA (alpha at end)
                    $newColor = sprintf('#%s%s%s%s', strtoupper($red), strtoupper($green), strtoupper($blue), strtoupper($alpha));
                    
                    DB::table('payment_methods')
                        ->where('id', $paymentMethod->id)
                        ->update(['background_color' => $newColor]);
                }
            });
    }

    /**
     * Reverse the migrations.
     * 
     * Converts payment method colors back from #RRGGBBAA format 
     * to #AARRGGBB format (alpha first)
     */
    public function down(): void
    {
        // Convert background_color from #RRGGBBAA back to #AARRGGBB
        DB::table('payment_methods')
            ->whereNotNull('background_color')
            ->where('background_color', 'like', '#________')
            ->get()
            ->each(function ($paymentMethod) {
                $color = $paymentMethod->background_color;
                // Check if it's in CSS format (#RRGGBBAA) - 8 hex digits
                if (preg_match('/^#([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})$/', $color, $matches)) {
                    $red = $matches[1];
                    $green = $matches[2];
                    $blue = $matches[3];
                    $alpha = $matches[4];
                    
                    // Convert back to old format: #AARRGGBB (alpha at start)
                    $oldColor = sprintf('#%s%s%s%s', strtoupper($alpha), strtoupper($red), strtoupper($green), strtoupper($blue));
                    
                    DB::table('payment_methods')
                        ->where('id', $paymentMethod->id)
                        ->update(['background_color' => $oldColor]);
                }
            });
    }
};
