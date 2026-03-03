<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webflow_sites', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')->nullable()->after('id');
        });

        $sites = DB::table('webflow_sites')->get(['id', 'addon_id']);
        $addonStoreIds = DB::table('addons')->pluck('store_id', 'id');
        foreach ($sites as $site) {
            $storeId = $addonStoreIds[$site->addon_id] ?? null;
            if ($storeId !== null) {
                DB::table('webflow_sites')->where('id', $site->id)->update(['store_id' => $storeId]);
            }
        }

        Schema::table('webflow_sites', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')->nullable(false)->change();
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
            $table->index('store_id');
        });

        Schema::table('webflow_sites', function (Blueprint $table) {
            $table->dropForeign(['addon_id']);
            $table->dropColumn('addon_id');
        });
    }

    public function down(): void
    {
        Schema::table('webflow_sites', function (Blueprint $table) {
            $table->foreignId('addon_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        $sites = DB::table('webflow_sites')->get();
        foreach ($sites as $site) {
            $addon = DB::table('addons')
                ->where('store_id', $site->store_id)
                ->where('type', 'webflow_cms')
                ->first();
            if ($addon) {
                DB::table('webflow_sites')->where('id', $site->id)->update(['addon_id' => $addon->id]);
            }
        }

        Schema::table('webflow_sites', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropColumn('store_id');
        });
    }
};
