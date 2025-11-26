<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            // Add slug column for tenant routing
            $table->string('slug')->nullable()->after('name');
            $table->index('slug');
        });

        // Generate slugs for existing stores
        \DB::table('stores')->get()->each(function ($store) {
            $slug = \Illuminate\Support\Str::slug($store->name ?? 'store-' . $store->id);
            \DB::table('stores')->where('id', $store->id)->update(['slug' => $slug]);
        });

        // Make slug not nullable after populating
        Schema::table('stores', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
        });

        // Remove team_id foreign key and column
        Schema::table('stores', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropColumn('team_id');
        });

        // Update team_user pivot to store_user
        if (Schema::hasTable('team_user')) {
            // Drop foreign key first
            Schema::table('team_user', function (Blueprint $table) {
                $table->dropForeign(['team_id']);
            });
            
            // Rename table
            Schema::rename('team_user', 'store_user');
            
            // Add new column, copy data, drop old column
            Schema::table('store_user', function (Blueprint $table) {
                $table->foreignId('store_id')->nullable()->after('team_id');
            });
            
            // Copy data from team_id to store_id
            \DB::statement('UPDATE store_user SET store_id = team_id');
            
            // Make store_id not nullable and add foreign key
            Schema::table('store_user', function (Blueprint $table) {
                $table->foreignId('store_id')->nullable(false)->change();
                $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
            });
            
            // Make team_id nullable for SQLite compatibility (can't drop columns in SQLite)
            if (config('database.default') === 'sqlite') {
                // SQLite doesn't support dropping columns, so make team_id nullable
                Schema::table('store_user', function (Blueprint $table) {
                    if (Schema::hasColumn('store_user', 'team_id')) {
                        $table->unsignedBigInteger('team_id')->nullable()->change();
                    }
                });
            } else {
                // For other databases, drop the column
                Schema::table('store_user', function (Blueprint $table) {
                    if (Schema::hasColumn('store_user', 'team_id')) {
                        $table->dropColumn('team_id');
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert store_user back to team_user
        if (Schema::hasTable('store_user')) {
            Schema::table('store_user', function (Blueprint $table) {
                $table->dropForeign(['store_id']);
                $table->foreignId('team_id')->nullable()->after('store_id');
            });
            
            // Copy data back
            \DB::statement('UPDATE store_user SET team_id = store_id');
            
            Schema::table('store_user', function (Blueprint $table) {
                $table->foreignId('team_id')->nullable(false)->change();
                $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
                $table->dropColumn('store_id');
            });
            
            Schema::rename('store_user', 'team_user');
        }

        Schema::table('stores', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->dropIndex(['slug']);
            $table->dropColumn('slug');
        });
    }
};
