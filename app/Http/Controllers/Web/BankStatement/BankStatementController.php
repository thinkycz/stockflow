<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\BankStatement;

use App\Enums\BankStatementStatusEnum;
use App\Http\Validation\BankStatementValidity;
use App\Models\BankStatement;
use App\Models\BankStatementTransaction;
use App\Models\Store;
use App\Models\User;
use App\Services\BankStatementReconciliationService;
use App\Services\BankStatementService;
use App\Support\ActiveStoreResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;

final class BankStatementController
{
    /**
     * Render upload and history for the active store.
     */
    public function index(Request $request): Response
    {
        $user = User::mustAuth();
        $store = ActiveStoreResolver::resolveIncludingInactive($request, $user);
        $statements = [];

        if ($store instanceof Store) {
            $query = BankStatement::query();
            BankStatement::scopeForUser($query, $user);
            BankStatement::scopeForStore($query, $store->getKey());
            $statements = $query->latest()->limit(50)->get()
                ->map(static fn(BankStatement $statement): array => self::summaryPayload($statement))
                ->all();
        }

        return Inertia::render('bank-statements/Index', [
            'statements' => $statements,
            'active_store' => $store instanceof Store ? [
                'id' => $store->getKey(),
                'name' => $store->getName(),
            ] : null,
        ]);
    }

    /**
     * Validate, archive, and queue one PDF.
     */
    public function store(Request $request, BankStatementService $service): RedirectResponse
    {
        $validity = BankStatementValidity::inject();
        $request->validate(['document' => $validity->document()->required()->toArray()]);
        $user = User::mustAuth();
        $store = ActiveStoreResolver::resolve($request, $user);

        if (!$store instanceof Store) {
            Thrower::default()->message('document', \__('Select an active store first.'))->throw();
        }

        $file = Typer::assertInstance($request->file('document'), UploadedFile::class);
        $result = $service->upload($user, $store, $file);

        if (!$result['created'] && $result['statement']->getStoreId() !== $store->getKey()) {
            $request->session()->put(ActiveStoreResolver::SESSION_KEY, $result['statement']->getStoreId());
        }

        if ($result['created'] && !$result['queued']) {
            Inertia::flash('error', \__('The bank statement could not be queued. Retry it from the import page.'));
        } else {
            Inertia::flash('success', $result['created'] ? \__('Bank statement queued.') : \__('This bank statement was already uploaded.'));
        }

        return Resolver::resolveRedirector()->route('bank-statements.show', ['bankStatement' => $result['statement']->getKey()]);
    }

    /**
     * Render metadata, editable rows, and live reconciliation.
     */
    public function show(
        Request $request,
        BankStatementService $service,
        BankStatementReconciliationService $reconciliation,
    ): Response {
        $statement = $this->activeStatement($request, $service, false);

        return Inertia::render('bank-statements/Show', [
            'statement' => self::detailPayload($statement),
            'transactions' => $statement->getTransactions()
                ->map(static fn(BankStatementTransaction $transaction): array => self::transactionPayload($transaction))
                ->all(),
            'reconciliation' => $reconciliation->forStatement($statement),
        ]);
    }

    /**
     * Download the original from private storage with strict cache headers.
     */
    public function original(Request $request, BankStatementService $service): StreamedResponse
    {
        $statement = $this->activeStatement($request, $service, false);
        $contents = $service->originalContents($statement);

        return Resolver::resolveResponseFactory()
            ->streamDownload(static function () use ($contents): void {
                echo $contents;
            }, $statement->getOriginalName(), [
                'Cache-Control' => 'private, no-store',
                'Content-Type' => 'application/pdf',
            ]);
    }

    /**
     * Replace all editable draft rows.
     */
    public function update(Request $request, BankStatementService $service): RedirectResponse
    {
        $validity = BankStatementValidity::inject();
        $nullableText = $validity->text()->nullable()->toArray();
        $nullableDate = $validity->optionalDate()->nullable()->toArray();
        $data = Typer::assertStringKeyArray(Typer::assertArray($request->validate([
            'transactions' => $validity->transactions()->required()->toArray(),
            'transactions.*.id' => $validity->rowId()->nullable()->toArray(),
            'transactions.*.booked_on' => $validity->date()->required()->toArray(),
            'transactions.*.executed_on' => $nullableDate,
            'transactions.*.item_type' => $validity->shortText()->required()->toArray(),
            'transactions.*.amount' => $validity->amount()->required()->toArray(),
            'transactions.*.currency' => $validity->currency()->required()->toArray(),
            'transactions.*.counterparty_name' => $nullableText,
            'transactions.*.counterparty_account' => $nullableText,
            'transactions.*.variable_symbol' => $nullableText,
            'transactions.*.constant_symbol' => $nullableText,
            'transactions.*.specific_symbol' => $nullableText,
            'transactions.*.description' => $nullableText,
            'transactions.*.category' => $validity->category()->required()->toArray(),
            'transactions.*.sales_from' => $nullableDate,
            'transactions.*.sales_to' => $nullableDate,
            'transactions.*.review_note' => $nullableText,
        ])));
        $rows = \array_map(
            static fn(mixed $row): array => Typer::assertStringKeyArray(Typer::assertArray($row)),
            Typer::assertArray($data['transactions']),
        );
        try {
            $service->updateDraft($this->activeStatement($request, $service), \array_values($rows));
        } catch (InvalidArgumentException $exception) {
            Thrower::default()->message('statement', \__($exception->getMessage()))->throw();
        }
        Inertia::flash('success', \__('Bank statement draft saved.'));

        return Resolver::resolveRedirector()->back();
    }

    /**
     * Confirm reviewed bank data without modifying reports.
     */
    public function confirm(Request $request, BankStatementService $service): RedirectResponse
    {
        $this->runLifecycle(
            static function (BankStatement $statement, User $user) use ($service): void {
                $service->confirm($statement, $user);
            },
            $request,
            $service,
        );
        Inertia::flash('success', \__('Bank statement confirmed.'));

        return Resolver::resolveRedirector()->back();
    }

    /**
     * Reopen a confirmed import for review.
     */
    public function reopen(Request $request, BankStatementService $service): RedirectResponse
    {
        $this->runLifecycle(
            static function (BankStatement $statement, User $user) use ($service): void {
                $service->reopen($statement, $user);
            },
            $request,
            $service,
        );
        Inertia::flash('success', \__('Bank statement reopened.'));

        return Resolver::resolveRedirector()->back();
    }

    /**
     * Queue a manual parsing retry.
     */
    public function retry(Request $request, BankStatementService $service): RedirectResponse
    {
        $statement = $this->activeStatement($request, $service);

        try {
            $queued = $service->retry($statement);
        } catch (InvalidArgumentException $exception) {
            Thrower::default()->message('statement', \__($exception->getMessage()))->throw();
        }

        Inertia::flash(
            $queued ? 'success' : 'error',
            $queued
                ? \__('Bank statement queued again.')
                : \__('The bank statement could not be queued. Retry it from the import page.'),
        );

        return Resolver::resolveRedirector()->back();
    }

    /**
     * Serialize a history row.
     *
     * @return array<string, int|string|null>
     */
    private static function summaryPayload(BankStatement $statement): array
    {
        return [
            'id' => $statement->getKey(),
            'status' => $statement->getStatus()->value,
            'bank_name' => $statement->getBankName(),
            'statement_number' => $statement->getStatementNumber(),
            'period_from' => $statement->getPeriodFrom()?->toDateString(),
            'period_to' => $statement->getPeriodTo()?->toDateString(),
            'currency' => $statement->getCurrency(),
            'original_name' => $statement->getOriginalName(),
            'attempt_count' => $statement->getAttemptCount(),
            'created_at' => $statement->getCreatedAt()->toIso8601String(),
        ];
    }

    /**
     * Serialize statement detail without exposing full account identifiers.
     *
     * @return array<string, bool|int|list<string>|string|null>
     */
    private static function detailPayload(BankStatement $statement): array
    {
        $storeActive = $statement->getStore()->isActive();

        return [
            ...self::summaryPayload($statement),
            'store_name' => $statement->getStore()->getName(),
            'store_active' => $storeActive,
            'account_number' => $statement->getMaskedAccountNumber(),
            'iban' => $statement->getMaskedIban(),
            'opening_balance' => $statement->getOpeningBalance(),
            'total_credits' => $statement->getTotalCredits(),
            'total_debits' => $statement->getTotalDebits(),
            'closing_balance' => $statement->getClosingBalance(),
            'credit_count' => $statement->getCreditCount(),
            'debit_count' => $statement->getDebitCount(),
            'parse_warnings' => $statement->getParseWarnings(),
            'last_error' => $statement->getLastError(),
            'editable' => $storeActive && $statement->getStatus() === BankStatementStatusEnum::REVIEW,
            'terminal' => \in_array($statement->getStatus(), [BankStatementStatusEnum::REVIEW, BankStatementStatusEnum::CONFIRMED, BankStatementStatusEnum::FAILED], true),
        ];
    }

    /**
     * Serialize an editable transaction row.
     *
     * @return array<string, bool|int|string|null>
     */
    private static function transactionPayload(BankStatementTransaction $transaction): array
    {
        return [
            'id' => $transaction->getKey(),
            'booked_on' => $transaction->getBookedOn()->toDateString(),
            'executed_on' => $transaction->getExecutedOn()?->toDateString(),
            'item_type' => $transaction->getItemType(),
            'amount' => $transaction->getAmount(),
            'currency' => $transaction->getCurrency(),
            'counterparty_name' => $transaction->getCounterpartyName(),
            'counterparty_account' => self::mask($transaction->getCounterpartyAccount()),
            'variable_symbol' => $transaction->getVariableSymbol(),
            'constant_symbol' => $transaction->getConstantSymbol(),
            'specific_symbol' => $transaction->getSpecificSymbol(),
            'description' => $transaction->getDescription(),
            'category' => $transaction->getCategory()->value,
            'sales_from' => $transaction->getSalesFrom()?->toDateString(),
            'sales_to' => $transaction->getSalesTo()?->toDateString(),
            'review_note' => $transaction->getReviewNote(),
            'manually_edited' => $transaction->isManuallyEdited(),
        ];
    }

    /**
     * Mask an external account identifier.
     */
    private static function mask(string|null $value): string|null
    {
        return $value === null || $value === '' ? null : '•••• ' . \mb_substr($value, -4);
    }

    /**
     * Resolve a statement and require it to belong to the currently active store.
     */
    private function activeStatement(Request $request, BankStatementService $service, bool $requireActiveStore = true): BankStatement
    {
        $user = User::mustAuth();
        $statement = $service->findOwned($user, Typer::parseInt($request->route('bankStatement')));
        $store = $requireActiveStore
            ? ActiveStoreResolver::resolve($request, $user)
            : ActiveStoreResolver::resolveIncludingInactive($request, $user);
        if (!$store instanceof Store || $statement->getStoreId() !== $store->getKey()) {
            \abort(404);
        }

        return $statement;
    }

    /**
     * Run a lifecycle transition and expose a safe validation error.
     *
     * @param callable(BankStatement, User): mixed $callback
     */
    private function runLifecycle(callable $callback, Request $request, BankStatementService $service): void
    {
        try {
            $callback($this->activeStatement($request, $service), User::mustAuth());
        } catch (InvalidArgumentException $exception) {
            Thrower::default()->message('statement', \__($exception->getMessage()))->throw();
        }
    }
}
