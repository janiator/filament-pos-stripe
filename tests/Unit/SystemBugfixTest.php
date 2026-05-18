<?php

use App\Models\ConnectedCustomer;
use App\Services\InventoryLedgerService;
use Illuminate\Database\QueryException;

class ConnectedCustomerArchiveSaveFailureStub extends ConnectedCustomer
{
    public function save(array $options = []): bool
    {
        return false;
    }
}

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

it('reports archive failure when the model cannot be saved', function () {
    $customer = new ConnectedCustomerArchiveSaveFailureStub;

    expect($customer->archive())->toBeFalse();
});

it('keeps FlutterFlow integer parsing helpers positive-only for string values', function () {
    $deferredPayment = file_get_contents(dirname(__DIR__, 2).'/docs/flutterflow/custom-actions/complete_deferred_payment.dart');
    $posPurchase = file_get_contents(dirname(__DIR__, 2).'/docs/flutterflow/custom-actions/complete_pos_purchase.dart');

    expect($deferredPayment)
        ->toContain("final parsed = int.tryParse(value.toString());\n\n  return parsed != null && parsed > 0 ? parsed : null;")
        ->and($posPurchase)
        ->toContain("final parsed = int.tryParse(value.toString());\n\n  return parsed != null && parsed > 0 ? parsed : null;")
        ->and($posPurchase)
        ->toContain("final parsed = int.tryParse(raw?.toString() ?? '');\n\n    return parsed != null && parsed > 0 ? parsed : null;");
});
