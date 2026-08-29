<?php

declare(strict_types=1);

use App\Ai\Tools\ConfiguredReadResourceTool;
use App\Models\Item;
use Laravel\Ai\Tools\Request;

\test('stockflow data tool returns bounded tenant scoped live inventory records', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    [$otherAdmin] = \createIsolatedUserWithWarehouse();
    Item::factory()->count(55)->create(['user_id' => $admin->getKey()]);
    Item::factory()->create(['user_id' => $otherAdmin->getKey(), 'title' => 'Other company secret']);
    $tool = new ConfiguredReadResourceTool($admin, 'query-conversation', 'read_items', 'items', 'Read items.', true);

    $result = \json_decode((string) $tool->handle(new Request([
        'search' => null,
        'limit' => 100,
    ], 'query-call', 'query-invocation')), true, 512, \JSON_THROW_ON_ERROR);

    \expect($result['resource'])->toBe('items')
        ->and($result['count'])->toBe(50)
        ->and($result['records'])->toHaveCount(50)
        ->and(\array_column($result['records'], 'title'))->not->toContain('Other company secret');
});
