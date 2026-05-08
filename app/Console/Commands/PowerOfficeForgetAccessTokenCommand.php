<?php

namespace App\Console\Commands;

use App\Enums\AddonType;
use App\Models\Addon;
use App\Models\Store;
use Illuminate\Console\Command;

/**
 * Clears cached OAuth access token so the next API call fetches a new JWT.
 *
 * Use after changing API privileges for the integration in PowerOffice Go (token embeds privilege claims).
 */
class PowerOfficeForgetAccessTokenCommand extends Command
{
    protected $signature = 'poweroffice:forget-token {store_slug : Store slug (e.g. jobberiet-as)}';

    protected $description = 'Clear cached PowerOffice OAuth token for a store (pick up new privileges after Go UI changes)';

    public function handle(): int
    {
        $slug = (string) $this->argument('store_slug');
        $store = Store::query()->where('slug', $slug)->first();
        if (! $store) {
            $this->error("Store not found for slug: {$slug}");

            return self::FAILURE;
        }

        if (! Addon::storeHasActiveAddon($store->getKey(), AddonType::PowerOfficeGo)) {
            $this->error('PowerOffice Go add-on is not active for this store.');

            return self::FAILURE;
        }

        $integration = $store->powerOfficeIntegration;
        if (! $integration) {
            $this->error('No PowerOffice integration for this store.');

            return self::FAILURE;
        }

        $integration->access_token = null;
        $integration->token_expires_at = null;
        $integration->save();

        $this->info('Cached access token cleared. The next sync or diagnose will request a new token from PowerOffice.');

        return self::SUCCESS;
    }
}
