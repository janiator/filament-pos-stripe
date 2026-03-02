<?php

namespace App\Services\Tripletex;

use Illuminate\Support\Facades\Http;

/**
 * Tripletex API client: session token and ledger voucher.
 * Mirrors Merano-Tripletex-Sync (tripletexApi.js) for use with POSitiv Z-reports.
 *
 * @see https://api.tripletex.io/v2/docs/
 */
class TripletexApiClient
{
    public function __construct(
        protected string $apiBaseUrl,
        protected string $consumerToken,
        protected string $employeeToken
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            config('tripletex.api_base_url'),
            config('tripletex.consumer_token', ''),
            config('tripletex.employee_token', '')
        );
    }

    /**
     * Create a session token (expires at given date).
     * Returns base64-encoded "0:token" for Authorization header.
     */
    public function getSessionToken(\DateTimeInterface $expirationDate): string
    {
        $expirationStr = $expirationDate->format('Y-m-d');
        $url = $this->apiBaseUrl.'/token/session/:create'
            .'?consumerToken='.urlencode($this->consumerToken)
            .'&employeeToken='.urlencode($this->employeeToken)
            .'&expirationDate='.urlencode($expirationStr);

        $response = Http::put($url);
        $response->throw();
        $token = $response->json('value.token');
        $encoded = base64_encode("0:{$token}");

        return $encoded;
    }

    /**
     * Create a voucher (sendToLedger=false to allow review before posting).
     *
     * @param  array{date: string, description: string, voucherType: array, postings: array}  $voucherData
     * @return array{value: array{id: int, ...}}
     */
    public function createVoucher(string $sessionToken, array $voucherData, bool $sendToLedger = false): array
    {
        $url = $this->apiBaseUrl.'/ledger/voucher?sendToLedger='.($sendToLedger ? 'true' : 'false');
        $response = Http::withHeaders([
            'Authorization' => 'Basic '.$sessionToken,
            'Content-Type' => 'application/json',
        ])->post($url, $voucherData);

        $response->throw();

        return $response->json();
    }

    /**
     * Get ledger account by number.
     *
     * @return array{id: int, number: int, ...}|null
     */
    public function getAccountByNumber(string $sessionToken, int $accountNumber): ?array
    {
        $url = $this->apiBaseUrl.'/ledger/account?number='.$accountNumber.'&from=0&count=1';
        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Basic '.$sessionToken,
        ])->get($url);

        $response->throw();
        $values = $response->json('values', []);

        return $values[0] ?? null;
    }
}
