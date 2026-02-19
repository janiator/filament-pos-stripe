<?php

use App\Enums\AddonType;
use App\Models\Addon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webflow_sites', function (Blueprint $table) {
            $table->foreignId('addon_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        $sites = DB::table('webflow_sites')->whereNotNull('store_id')->get();
        foreach ($sites as $site) {
            $addon = Addon::firstOrCreate(
                [
                    'store_id' => $site->store_id,
                    'type' => AddonType::WebflowCms,
                ],
                ['is_active' => true]
            );
            DB::table('webflow_sites')->where('id', $site->id)->update(['addon_id' => $addon->id]);
        }

        Schema::table('webflow_sites', function (Blueprint $table) {
            $table->foreignId('addon_id')->nullable(false)->change();
        });

        Schema::table('webflow_sites', function (Blueprint $table) {
            $table->dropIndex('webflow_sites_store_id_index');
            $table->dropColumn('store_id');
        });
    }

    public function down(): void
    {
        Schema::table('webflow_sites', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')->nullable()->after('id');
            $table->index('store_id');
        });

        $sites = DB::table('webflow_sites')->get();
        foreach ($sites as $site) {
            $addon = Addon::find($site->addon_id);
            if ($addon) {
                DB::table('webflow_sites')->where('id', $site->id)->update(['store_id' => $addon->store_id]);
            }
        }

        Schema::table('webflow_sites', function (Blueprint $table) {
            $table->dropForeign(['addon_id']);
            $table->dropColumn('addon_id');
        });
    }
};
