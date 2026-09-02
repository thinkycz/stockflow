<?php

declare(strict_types=1);

use App\Models\Store;
use App\Models\User;
use App\Support\ActiveStoreResolver;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Thinkycz\LaravelCore\Support\Typer;

\pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Architecture', 'Feature', 'Unit');

/**
 * Recursively iterate every .php file under a directory.
 *
 * @return iterable<string>
 */
function arch_php_files(string $dir): iterable
{
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

    foreach ($rii as $file) {
        /** @var SplFileInfo $file */
        if ($file->isFile() && $file->getExtension() === 'php') {
            yield $file->getPathname();
        }
    }
}

/**
 * @return array{0: User, 1: Store}
 */
function createIsolatedUserWithWarehouse(): array
{
    $user = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $warehouse = Store::factory()->warehouse()->create([
        'user_id' => $user->getKey(),
    ]);

    return [$user, $warehouse];
}

/**
 * Build the browser-session payload for an active store.
 *
 * @return array{active_store_id: int}
 */
function activeStoreSession(Store $store): array
{
    return [ActiveStoreResolver::SESSION_KEY => $store->getKey()];
}

/**
 * Build one structurally balanced parser response shared by bank statement tests.
 *
 * @return array<string, mixed>
 */
function parsedBankStatementPayload(): array
{
    return [
        'bank_code' => '0800',
        'bank_name' => 'Česká spořitelna',
        'account_name' => 'Company',
        'account_number' => '123456789/0800',
        'iban' => 'CZ0008000000000123456789',
        'bic' => 'GIBACZPX',
        'currency' => 'CZK',
        'statement_number' => '8',
        'period_from' => '2026-08-01',
        'period_to' => '2026-08-31',
        'opening_balance' => '100.00',
        'total_credits' => '1000.00',
        'total_debits' => '250.00',
        'closing_balance' => '850.00',
        'available_balance' => '850.00',
        'credit_count' => 1,
        'debit_count' => 1,
        'transactions' => [
            [
                'booked_on' => '2026-08-02',
                'executed_on' => null,
                'item_type' => 'Incoming transfer',
                'amount' => '1000.00',
                'currency' => 'CZK',
                'counterparty_name' => 'Card processor',
                'counterparty_account' => null,
                'variable_symbol' => null,
                'constant_symbol' => null,
                'specific_symbol' => '20260801',
                'description' => null,
                'category' => 'card',
                'sales_from' => '2026-08-01',
                'sales_to' => '2026-08-01',
                'review_note' => null,
            ],
            [
                'booked_on' => '2026-08-03',
                'executed_on' => null,
                'item_type' => 'Outgoing transfer',
                'amount' => '-250.00',
                'currency' => 'CZK',
                'counterparty_name' => null,
                'counterparty_account' => null,
                'variable_symbol' => null,
                'constant_symbol' => null,
                'specific_symbol' => null,
                'description' => null,
                'category' => 'outgoing',
                'sales_from' => null,
                'sales_to' => null,
                'review_note' => null,
            ],
        ],
    ];
}

/**
 * Assert that the response carries an Inertia flash message
 * (success or error) under the given key.
 *
 * Works for both redirect responses (via the Inertia re-flash
 * mechanism) and 200 OK Inertia render responses (via the
 * `flash` prop the HandleInertiaRequests middleware injects).
 */
function assertInertiaFlash(TestResponse $response, string $key, mixed $message): void
{
    try {
        $response->assertInertiaFlash($key, $message);

        return;
    } catch (Throwable) {
        // Fall through to the props check for 200 OK render responses.
    }

    $flashed = $response->json('props.flash.' . $key);

    \expect($flashed)->toBe($message);
}
