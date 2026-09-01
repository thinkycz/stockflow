<?php

declare(strict_types=1);

use Thinkycz\LaravelCore\Support\Resolver;

\test('users table does not persist an account-wide active store', function (): void {
    \expect(Resolver::resolveSchemaBuilder()->hasColumn('users', 'active_store_id'))->toBeFalse();
});
