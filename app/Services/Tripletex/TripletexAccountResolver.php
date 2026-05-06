<?php

namespace App\Services\Tripletex;

use App\Exceptions\Tripletex\TripletexUnresolvedLedgerAccountsException;
use App\Models\TripletexIntegration;

class TripletexAccountResolver
{
    public function __construct(
        protected TripletexApiClient $apiClient,
    ) {}

    /**
     * @param  list<string>  $accountCodes
     * @return array<string, array<string, mixed>> account number string => Tripletex account object
     */
    public function resolveMapForAccountNos(TripletexIntegration $integration, string $sessionToken, array $accountCodes): array
    {
        $accountCodes = array_values(array_unique(array_map(
            static fn (string $c): string => trim($c),
            $accountCodes,
        )));

        $map = [];
        $missing = [];

        foreach ($accountCodes as $code) {
            if ($code === '') {
                continue;
            }
            $account = $this->apiClient->getAccountByNumber($sessionToken, $integration->environment, $code);
            if ($account === null) {
                $missing[] = $code;

                continue;
            }
            $map[$code] = $account;
        }

        if ($missing !== []) {
            throw new TripletexUnresolvedLedgerAccountsException($missing);
        }

        return $map;
    }
}
