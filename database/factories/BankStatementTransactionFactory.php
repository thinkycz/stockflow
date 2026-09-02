<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BankStatementTransactionCategoryEnum;
use App\Models\BankStatement;
use App\Models\BankStatementTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankStatementTransaction>
 */
class BankStatementTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bank_statement_id' => static fn(): int => BankStatement::factory()->createOne()->getKey(),
            'position' => $this->faker->unique()->numberBetween(1, 100000),
            'booked_on' => '2026-08-02',
            'executed_on' => '2026-08-01',
            'item_type' => 'Tuzemská příchozí úhrada kartou',
            'amount' => '990.00',
            'currency' => 'CZK',
            'counterparty_name' => 'Global Payments s.r.o.',
            'counterparty_account' => '55550309/0800',
            'variable_symbol' => '0903660002',
            'constant_symbol' => null,
            'specific_symbol' => '20260801',
            'description' => 'AKCEPTACE PLATEBNICH KARET',
            'category' => BankStatementTransactionCategoryEnum::CARD->value,
            'sales_from' => '2026-08-01',
            'sales_to' => '2026-08-01',
            'review_note' => null,
            'source_payload' => [],
            'manually_edited' => false,
        ];
    }

    /**
     * Associate the transaction with a bank statement.
     */
    public function forStatement(BankStatement $statement): self
    {
        return $this->state(fn(): array => [
            'bank_statement_id' => $statement->getKey(),
        ]);
    }
}
