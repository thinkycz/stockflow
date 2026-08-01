<?php

declare(strict_types=1);

use App\Models\FinancialRecurringExpense;
use App\Models\FinancialRecurringExpenseVersion;
use App\Models\Store;
use Database\Factories\UserFactory;

\test('admin creates changes lists and terminates a recurring expense', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $base = '/income-expenses/recurring-expenses';
    $storeQuery = '?store_id=' . $store->getKey();
    $context = ['year' => 2026, 'month' => 7];
    $fields = ['label' => 'Internet', 'amount' => 900, 'due_day' => 20, 'note' => 'Fiber'];

    $this->be($admin, 'users')->post($base . $storeQuery, [
        ...$context, ...$fields, 'effective_period' => '2026-07',
    ])->assertRedirect();
    $expense = FinancialRecurringExpense::query()->firstOrFail();
    \expect(FinancialRecurringExpenseVersion::query()->count())->toBe(1);

    $this->be($admin, 'users')->put($base . '/' . $expense->getKey() . $storeQuery, [
        ...$context, ...$fields, 'amount' => 1000, 'effective_period' => '2026-08',
    ])->assertRedirect();
    \expect(FinancialRecurringExpenseVersion::query()->count())->toBe(2);

    $this->be($admin, 'users')->get('/income-expenses?store_id=' . $store->getKey() . '&year=2026&month=8', $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.recurring_expenses.0.label', 'Internet')
        ->assertJsonPath('props.recurring_expenses.0.amount', 1000)
        ->assertJsonPath('props.recurring_expenses.0.status', 'active');

    $this->be($admin, 'users')->post($base . '/' . $expense->getKey() . '/terminate' . $storeQuery, [
        ...$context, 'ends_before_period' => '2026-09',
    ])->assertRedirect();
    \expect($expense->fresh()?->getEndsBefore())->toBe('2026-09-01');
});

\test('recurring expense mutations reject warehouses foreign stores and limited users', function (): void {
    [$admin, $warehouse] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $otherStore = Store::factory()->create(['user_id' => $admin->getKey()]);
    $limited = UserFactory::new()->limited($store)->createOne();
    $payload = [
        'year' => 2026, 'month' => 7, 'effective_period' => '2026-07',
        'label' => 'Rent', 'amount' => 1000, 'due_day' => 1, 'note' => null,
    ];

    $this->be($admin, 'users')->post('/income-expenses/recurring-expenses?store_id=' . $warehouse->getKey(), $payload)->assertNotFound();
    $this->be($admin, 'users')->post('/income-expenses/recurring-expenses?store_id=' . $store->getKey(), $payload)->assertRedirect();
    $expense = FinancialRecurringExpense::query()->firstOrFail();
    $this->be($admin, 'users')->put('/income-expenses/recurring-expenses/' . $expense->getKey() . '?store_id=' . $otherStore->getKey(), $payload)->assertNotFound();
    $this->be($limited, 'users')->post('/income-expenses/recurring-expenses?store_id=' . $store->getKey(), $payload)->assertRedirect('/dashboard');
});
