<?php

namespace App\Support\Tripletex;

/**
 * Shrinks period preview JSON before persisting on {@see \App\Models\TripletexIntegration::$period_preview_state}
 * so MySQL packet limits, JSON column size, and Livewire snapshot size stay safe.
 */
final class TripletexPeriodPreviewPayloadForStorage
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public static function prepare(array $payload, bool $forceEmergencyRollupOnly = false, ?int $maxStoredJsonBytesOverride = null): array
    {
        if ($forceEmergencyRollupOnly) {
            $rollupOnly = self::keepRollupOnly($payload);

            return [
                $rollupOnly,
                [
                    'steps' => ['forced_emergency_rollup_only'],
                    'approx_bytes_before' => self::jsonLength($payload),
                    'approx_bytes_after' => self::jsonLength($rollupOnly),
                    'max_bytes_target' => 0,
                ],
            ];
        }

        $maxBytes = $maxStoredJsonBytesOverride ?? max(50_000, (int) config('tripletex.period_preview.max_stored_json_bytes', 1_500_000));
        $steps = [];
        $working = $payload;

        $before = self::jsonLength($working);
        $working = self::removeKeysRecursive($working, ['tripletex_voucher_payload', 'tripletex_postings_display']);
        if (self::jsonLength($working) < $before) {
            $steps[] = 'removed_tripletex_voucher_payload_fields';
        }

        if (self::jsonLength($working) > $maxBytes) {
            $working = self::removeKeysRecursive($working, ['lines_display']);
            $steps[] = 'removed_lines_display';
        }

        if (self::jsonLength($working) > $maxBytes) {
            $working = self::slimZAndPayoutRowPreviews($working);
            $steps[] = 'slimmed_row_previews';
        }

        if (self::jsonLength($working) > $maxBytes) {
            unset($working['aggregate_vouchers']);
            $steps[] = 'dropped_aggregate_vouchers';
        }

        if (self::jsonLength($working) > $maxBytes) {
            $working = self::truncateRowLists($working, 60);
            $steps[] = 'truncated_z_and_payout_row_lists';
        }

        if (self::jsonLength($working) > $maxBytes) {
            $working = self::keepRollupOnly($working);
            $steps[] = 'rollup_only_emergency';
        }

        $after = self::jsonLength($working);

        return [
            $working,
            [
                'steps' => $steps,
                'approx_bytes_before' => $before,
                'approx_bytes_after' => $after,
                'max_bytes_target' => $maxBytes,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    public static function removeKeysRecursive(array $node, array $keys): array
    {
        foreach ($keys as $k) {
            unset($node[$k]);
        }
        foreach ($node as $k => $v) {
            if (is_array($v)) {
                $node[$k] = self::removeKeysRecursive($v, $keys);
            }
        }

        return $node;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected static function slimZAndPayoutRowPreviews(array $payload): array
    {
        foreach (['z_reports', 'payouts'] as $listKey) {
            if (! isset($payload[$listKey]) || ! is_array($payload[$listKey])) {
                continue;
            }
            foreach ($payload[$listKey] as $i => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $preview = $row['preview'] ?? null;
                if (! is_array($preview)) {
                    continue;
                }
                $payload[$listKey][$i]['preview'] = self::minimalPreviewForStorage($preview);
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $preview
     * @return array<string, mixed>
     */
    protected static function minimalPreviewForStorage(array $preview): array
    {
        $out = [
            'ok' => (bool) ($preview['ok'] ?? false),
            'kind' => $preview['kind'] ?? null,
            'balanced' => (bool) ($preview['balanced'] ?? false),
            'debit_total_minor' => (int) ($preview['debit_total_minor'] ?? 0),
            'credit_total_minor' => (int) ($preview['credit_total_minor'] ?? 0),
            'error' => $preview['error'] ?? null,
            'resolve_error' => $preview['resolve_error'] ?? null,
        ];
        if (($preview['kind'] ?? '') === 'payout') {
            $out['mirror_balance_transaction_count'] = $preview['mirror_balance_transaction_count'] ?? null;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected static function truncateRowLists(array $payload, int $maxRows): array
    {
        foreach (['z_reports', 'payouts'] as $listKey) {
            if (! isset($payload[$listKey]) || ! is_array($payload[$listKey])) {
                continue;
            }
            $list = $payload[$listKey];
            if (count($list) > $maxRows) {
                $payload[$listKey] = array_slice($list, 0, $maxRows);
                $payload['_storage_'.$listKey.'_truncated_to'] = $maxRows;
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected static function keepRollupOnly(array $payload): array
    {
        return [
            'ok' => (bool) ($payload['ok'] ?? true),
            'period' => is_array($payload['period'] ?? null) ? $payload['period'] : [],
            'limits' => is_array($payload['limits'] ?? null) ? $payload['limits'] : [],
            'rollup' => is_array($payload['rollup'] ?? null) ? $payload['rollup'] : [],
            '_storage_emergency_rollup_only' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $value
     */
    protected static function jsonLength(array $value): int
    {
        $json = json_encode($value, JSON_INVALID_UTF8_SUBSTITUTE);

        return $json === false ? 0 : strlen($json);
    }
}
