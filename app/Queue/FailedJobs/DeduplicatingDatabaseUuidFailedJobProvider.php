<?php

namespace App\Queue\FailedJobs;

use Illuminate\Queue\Console\RetryCommand;
use Illuminate\Queue\Failed\DatabaseUuidFailedJobProvider;

/**
 * Ensures inserting a failed job never violates failed_jobs.uuid uniqueness.
 *
 * Retried queue jobs reuse the payload UUID ({@see RetryCommand}),
 * while the original failure may still occupy failed_jobs briefly. Laravel records the retry
 * failure with the same UUID, triggering a duplicate-key error on PostgreSQL/MySQL/etc.
 *
 * Deleting any existing row for the payload UUID matches "latest failure wins" semantics.
 */
final class DeduplicatingDatabaseUuidFailedJobProvider extends DatabaseUuidFailedJobProvider
{
    /**
     * {@inheritdoc}
     */
    public function log($connection, $queue, $payload, $exception)
    {
        $decodedPayload = json_decode($payload, true);

        $uuid = is_array($decodedPayload) ? ($decodedPayload['uuid'] ?? null) : null;

        if (is_string($uuid) && $uuid !== '') {
            $this->getTable()->where('uuid', $uuid)->delete();
        }

        return parent::log($connection, $queue, $payload, $exception);
    }
}
