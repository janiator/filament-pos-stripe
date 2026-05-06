<?php

use App\Services\InventoryLedgerService;
use Illuminate\Database\QueryException;

function inventoryLedgerDuplicateKeyResult(array $errorInfo): bool
{
    $previous = new PDOException('Duplicate key violation');
    $previous->errorInfo = $errorInfo;

    $exception = new QueryException('pgsql', 'insert into inventory_stock_movements (...) values (...)', [], $previous);
    $method = new ReflectionMethod(InventoryLedgerService::class, 'isDuplicateKey');
    $method->setAccessible(true);

    return $method->invoke(new InventoryLedgerService, $exception);
}

it('recognizes PostgreSQL duplicate key SQLSTATE values', function () {
    expect(inventoryLedgerDuplicateKeyResult(['23505', 7, 'duplicate key value violates unique constraint']))->toBeTrue();
});

it('does not leave debug logging in the variants relation manager', function () {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Resources/ConnectedProducts/RelationManagers/VariantsRelationManager.php');

    expect($contents)->not->toContain('\\Log::debug(');
});
