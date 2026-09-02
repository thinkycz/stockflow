<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BankStatementStatusEnum;
use App\Models\BankStatement;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankStatement>
 */
class BankStatementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => static fn(): int => UserFactory::new()->admin()->createOne()->getKey(),
            'store_id' => static fn(): int => Store::factory()->createOne()->getKey(),
            'uploaded_by_user_id' => static fn(): int => UserFactory::new()->admin()->createOne()->getKey(),
            'status' => BankStatementStatusEnum::REVIEW->value,
            'bank_code' => '0800',
            'bank_name' => 'Česká spořitelna',
            'account_name' => 'Test Company',
            'account_number' => '123456789/0800',
            'iban' => 'CZ0008000000000123456789',
            'bic' => 'GIBACZPX',
            'currency' => 'CZK',
            'statement_number' => (string) $this->faker->unique()->numberBetween(1, 9999),
            'period_from' => '2026-08-01',
            'period_to' => '2026-08-31',
            'opening_balance' => '100.00',
            'total_credits' => '1000.00',
            'total_debits' => '0.00',
            'closing_balance' => '1100.00',
            'available_balance' => '1100.00',
            'credit_count' => 1,
            'debit_count' => 0,
            'original_path' => 'bank-statements/test.pdf',
            'original_name' => 'statement.pdf',
            'original_mime' => 'application/pdf',
            'original_size' => 1024,
            'sha256' => $this->faker->unique()->sha256(),
            'parse_warnings' => [],
            'raw_ai_response' => [],
            'last_error' => null,
            'attempt_count' => 1,
            'queued_at' => \now(),
            'started_at' => \now(),
            'parsed_at' => \now(),
        ];
    }

    /**
     * Associate the statement with one store and its owner.
     */
    public function forStore(Store $store): self
    {
        return $this->state(fn(): array => [
            'user_id' => $store->getUserId(),
            'store_id' => $store->getKey(),
            'uploaded_by_user_id' => $store->getUserId(),
        ]);
    }
}
