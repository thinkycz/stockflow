<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

\test('recurring expense migration repairs a partially applied foreign key', function (): void {
    $schema = Resolver::resolveSchemaBuilder();
    $schema->table('financial_recurring_expense_versions', static function (Blueprint $table): void {
        $table->dropForeign(['financial_recurring_expense_id']);
    });

    \expect($schema->hasForeignKey('financial_recurring_expense_versions', ['financial_recurring_expense_id']))->toBeFalse();

    $migration = Typer::assertInstance(
        require \database_path('migrations/2026_08_01_000004_create_financial_recurring_expenses.php'),
        Migration::class,
    );
    $migration->up();

    \expect($schema->hasForeignKey('financial_recurring_expense_versions', ['financial_recurring_expense_id']))->toBeTrue();
});
