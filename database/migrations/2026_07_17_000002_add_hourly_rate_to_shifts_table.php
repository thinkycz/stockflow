<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->table('shifts', static function (Blueprint $table): void {
            $table->decimal('hourly_rate', 10, 2)->default(0)->after('end_time');
        });

        $connection = Resolver::resolveDatabaseManager()->connection();
        $connection->table('shifts')->update([
            'hourly_rate' => $connection->raw('(SELECT hourly_rate FROM workers WHERE workers.id = shifts.worker_id)'),
        ]);
    }
};
