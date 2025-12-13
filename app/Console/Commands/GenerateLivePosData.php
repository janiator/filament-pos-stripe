<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Models\User;
use App\Models\PosDevice;
use App\Models\PosSession;
use App\Models\ConnectedCharge;
use App\Models\ConnectedProduct;
use App\Models\PosEvent;
use App\Models\Receipt;
use App\Services\ReceiptGenerationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateLivePosData extends Command
{
    protected $signature = 'pos:generate-live-data 
                            {--store= : Store slug or ID}
                            {--sessions=5 : Number of sessions to create}
                            {--transactions=10 : Transactions per session}';

    protected $description = 'Generate live POS data for testing in Filament (writes to database)';

    public function handle(): void
    {
        $this->info('🚀 Generating live POS data...');
        $this->newLine();

        // Get or create store
        $storeSlug = $this->option('store');
        if ($storeSlug) {
            $store = Store::where('slug', $storeSlug)
                ->orWhere('id', is_numeric($storeSlug) ? (int) $storeSlug : null)
                ->first();
            
            if (!$store) {
                $this->error("Store not found: {$storeSlug}");
                return;
            }
        } else {
            $store = Store::first();
            if (!$store) {
                $this->error('No store found. Please create a store first.');
                return;
            }
        }

        $this->info("📦 Using store: {$store->name} (ID: {$store->id})");

        // Get or create user
        $user = User::first();
        if (!$user) {
            $this->error('No user found. Please create a user first.');
            return;
        }

        // Get or create POS device
        $device = PosDevice::where('store_id', $store->id)->first();
        if (!$device) {
            $device = PosDevice::create([
                'store_id' => $store->id,
                'device_name' => 'Test POS Device',
                'device_identifier' => 'test-device-' . uniqid(),
                'platform' => 'web',
                'device_status' => 'active',
            ]);
            $this->info("📱 Created POS device: {$device->device_name}");
        }

        // Get stripe_account_id from store
        $stripeAccountId = $store->stripe_account_id ?? $store->id; // Fallback to store ID if not set

        // Create some products if they don't exist
        $products = ConnectedProduct::where('stripe_account_id', $stripeAccountId)
            ->where('active', true)
            ->limit(10)
            ->get();

        if ($products->isEmpty()) {
            $this->info('📦 Creating sample products...');
            for ($i = 1; $i <= 10; $i++) {
                ConnectedProduct::create([
                    'stripe_product_id' => 'prod_test_' . uniqid(),
                    'stripe_account_id' => $stripeAccountId,
                    'name' => "Product {$i}",
                    'description' => "Sample product {$i}",
                    'active' => true,
                    'price' => rand(50, 500) . '.' . str_pad(rand(0, 99), 2, '0', STR_PAD_LEFT),
                    'currency' => 'nok',
                    'article_group_code' => '04003',
                    'product_code' => 'PLU' . str_pad($i, 3, '0', STR_PAD_LEFT),
                ]);
            }
            $products = ConnectedProduct::where('stripe_account_id', $stripeAccountId)->get();
            $this->info("✅ Created {$products->count()} products");
        }

        $numSessions = (int) $this->option('sessions');
        $transactionsPerSession = (int) $this->option('transactions');

        $this->info("📊 Creating {$numSessions} sessions with {$transactionsPerSession} transactions each...");
        $this->newLine();

        $receiptService = app(ReceiptGenerationService::class);

        for ($sessionNum = 1; $sessionNum <= $numSessions; $sessionNum++) {
            $this->info("Session {$sessionNum}/{$numSessions}...");

            // Open session
            $openedAt = now()->subDays(rand(0, 7))->subHours(rand(0, 23));
            $openingBalance = rand(5000, 20000); // 50-200 NOK

            $session = PosSession::create([
                'store_id' => $store->id,
                'pos_device_id' => $device->id,
                'user_id' => $user->id,
                'session_number' => $this->generateSessionNumber($store->id),
                'status' => $sessionNum === $numSessions ? 'open' : 'closed', // Last session stays open
                'opened_at' => $openedAt,
                'closed_at' => $sessionNum === $numSessions ? null : $openedAt->copy()->addHours(rand(4, 8)),
                'opening_balance' => $openingBalance,
                'opening_notes' => "Session {$sessionNum} - Generated test data",
            ]);

            $this->line("  ✓ Session opened: {$session->session_number}");

            // Create transactions
            $totalCash = 0;
            $totalCard = 0;
            $totalMobile = 0;

            for ($t = 1; $t <= $transactionsPerSession; $t++) {
                $product = $products->random();
                $paymentMethod = ['cash', 'card', 'card', 'mobile'][rand(0, 3)]; // More card payments
                $amount = (int) (floatval($product->price) * 100 * rand(1, 3)); // 1-3 units
                $paidAt = $openedAt->copy()->addMinutes(rand(1, 480));

                $charge = ConnectedCharge::create([
                    'stripe_charge_id' => 'ch_test_' . uniqid(),
                    'stripe_account_id' => $stripeAccountId,
                    'pos_session_id' => $session->id,
                    'amount' => $amount,
                    'currency' => 'nok',
                    'status' => 'succeeded',
                    'paid' => true,
                    'paid_at' => $paidAt,
                    'payment_method' => $paymentMethod,
                    'description' => $product->name,
                    'captured' => true,
                    'refunded' => false,
                    'transaction_code' => $paymentMethod === 'cash' ? '11001' : '11002',
                    'payment_code' => match($paymentMethod) {
                        'cash' => '12001',
                        'card' => '12002',
                        'mobile' => '12011',
                        default => '12999',
                    },
                    'article_group_code' => $product->article_group_code ?? '04003',
                    'tip_amount' => rand(0, 100) > 70 ? rand(50, 500) : 0, // 30% chance of tip
                ]);

                // Create receipt for some transactions
                if (rand(0, 100) > 30) { // 70% get receipts
                    try {
                        $receipt = $receiptService->generateSalesReceipt($charge, $session);
                        $this->line("    ✓ Receipt: {$receipt->receipt_number}");
                    } catch (\Throwable $e) {
                        $this->warn("    ⚠ Receipt error: " . $e->getMessage());
                    }
                }

                match($paymentMethod) {
                    'cash' => $totalCash += $amount,
                    'card' => $totalCard += $amount,
                    'mobile' => $totalMobile += $amount,
                    default => null,
                };
            }

            // Close session (except last one)
            if ($sessionNum !== $numSessions) {
                $expectedCash = $totalCash;
                $actualCash = $expectedCash + rand(-200, 200); // Small variance
                $cashDifference = $actualCash - $expectedCash;

                $session->update([
                    'status' => 'closed',
                    'closed_at' => $openedAt->copy()->addHours(rand(4, 8)),
                    'expected_cash' => $expectedCash,
                    'actual_cash' => $actualCash,
                    'cash_difference' => $cashDifference,
                    'closing_notes' => "Session closed - Cash difference: " . number_format($cashDifference / 100, 2) . " NOK",
                ]);

                $this->line("  ✓ Session closed: Expected: " . number_format($expectedCash / 100, 2) . " NOK, Actual: " . number_format($actualCash / 100, 2) . " NOK");
            }

            $this->line("  📊 Transactions: Cash: " . number_format($totalCash / 100, 2) . " NOK, Card: " . number_format($totalCard / 100, 2) . " NOK");
            $this->newLine();
        }

        $this->newLine();
        $this->info('✅ Live POS data generated successfully!');
        $this->info("📊 You can now view the data in Filament at: /pos-sessions");
        $this->newLine();
        $this->info("Summary:");
        $this->line("  - Store: {$store->name}");
        $this->line("  - Sessions created: {$numSessions}");
        $this->line("  - Total transactions: " . ($numSessions * $transactionsPerSession));
        $this->line("  - Products available: {$products->count()}");
    }

    protected function generateSessionNumber(int $storeId): string
    {
        $lastSession = PosSession::where('store_id', $storeId)
            ->orderBy('session_number', 'desc')
            ->first();

        if ($lastSession && preg_match('/(\d+)$/', $lastSession->session_number, $matches)) {
            $nextNumber = (int) $matches[1] + 1;
        } else {
            $nextNumber = 1;
        }

        return str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }
}

