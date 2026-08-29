<?php

declare(strict_types=1);

use App\Ai\AssistantToolCatalog;
use App\Models\Item;
use App\Models\Shift;
use App\Models\Store;
use App\Models\Worker;
use Illuminate\Support\Carbon;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

\test('every bounded read explicitly reports and continues partial results', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    [$otherAdmin] = \createIsolatedUserWithWarehouse();
    Item::factory()->count(55)->create(['user_id' => $admin->getKey()]);
    Item::factory()->create(['user_id' => $otherAdmin->getKey(), 'title' => 'Other company secret']);
    $tool = (new AssistantToolCatalog())->find($admin, 'query-conversation', 'read_items');

    \expect($tool)->toBeInstanceOf(Tool::class);
    $first = \readToolResult($tool, [
        'request' => ['operation' => 'list', 'limit' => 50],
    ]);
    $second = \readToolResult($tool, [
        'request' => [
            'operation' => 'list',
            'limit' => 50,
            'cursor' => $first['next_cursor'],
        ],
    ]);
    $titles = [
        ...\array_column($first['records'], 'title'),
        ...\array_column($second['records'], 'title'),
    ];

    \expect($first)->toMatchArray([
        'version' => 2,
        'resource' => 'items',
        'operation' => 'list',
        'returned_count' => 50,
        'complete' => false,
        'has_more' => true,
        'warnings' => ['PARTIAL_RESULT'],
    ])->and($first['as_of'])->toBeString()
        ->and($first['next_cursor'])->toBeString()
        ->and($second['returned_count'])->toBe(5)
        ->and($second['complete'])->toBeTrue()
        ->and($second['has_more'])->toBeFalse()
        ->and($second['next_cursor'])->toBeNull()
        ->and($titles)->toHaveCount(55)
        ->and(\array_unique($titles))->toHaveCount(55)
        ->and($titles)->not->toContain('Other company secret');
});

\test('read cursors are encrypted and bound to actor resource and normalized filters', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    [$otherAdmin] = \createIsolatedUserWithWarehouse();
    foreach (['Alpha', 'Beta', 'Gamma'] as $title) {
        Item::factory()->create(['user_id' => $admin->getKey(), 'title' => $title]);
    }
    $tool = (new AssistantToolCatalog())->find($admin, 'cursor-security', 'read_items');
    $otherTool = (new AssistantToolCatalog())->find($otherAdmin, 'cursor-security', 'read_items');

    \expect($tool)->toBeInstanceOf(Tool::class)
        ->and($otherTool)->toBeInstanceOf(Tool::class);
    $first = \readToolResult($tool, [
        'request' => ['operation' => 'list', 'search' => 'a', 'limit' => 1],
    ]);
    $cursor = $first['next_cursor'];

    \expect($cursor)->toBeString();
    \expect(fn(): array => \readToolResult($tool, [
        'request' => ['operation' => 'list', 'search' => 'different', 'limit' => 1, 'cursor' => $cursor],
    ]))->toThrow(InvalidArgumentException::class)
        ->and(fn(): array => \readToolResult($otherTool, [
            'request' => ['operation' => 'list', 'search' => 'a', 'limit' => 1, 'cursor' => $cursor],
        ]))->toThrow(InvalidArgumentException::class)
        ->and(fn(): array => \readToolResult($tool, [
            'request' => ['operation' => 'list', 'search' => 'a', 'limit' => 1, 'cursor' => $cursor . 'tampered'],
        ]))->toThrow(InvalidArgumentException::class);
});

\test('shift month summary includes early dates beyond the first raw page', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->createOne(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->createOne(['user_id' => $admin->getKey()]);

    foreach (\range(0, 59) as $offset) {
        Shift::factory()->createOne([
            'user_id' => $admin->getKey(),
            'store_id' => $store->getKey(),
            'worker_id' => $worker->getKey(),
            'date' => Carbon::parse('2026-09-01')->addDays(\intdiv($offset, 2))->toDateString(),
            'start_time' => $offset % 2 === 0 ? '08:00' : '12:00',
            'end_time' => $offset % 2 === 0 ? '12:00' : '16:00',
            'hourly_rate' => $worker->getHourlyRate(),
        ]);
    }

    $tool = (new AssistantToolCatalog())->find($admin, 'shift-summary', 'read_shifts');

    \expect($tool)->toBeInstanceOf(Tool::class);
    $result = \readToolResult($tool, [
        'request' => [
            'operation' => 'summary',
            'store_id' => $store->getKey(),
            'year' => 2026,
            'month' => 9,
        ],
    ]);

    \expect($result)->toMatchArray([
        'version' => 2,
        'resource' => 'shifts',
        'operation' => 'summary',
        'complete' => true,
        'has_more' => false,
    ])->and($result['summary']['shift_count'])->toBe(60)
        ->and($result['summary']['scheduled_days'])->toBe(30)
        ->and($result['summary']['first_shift_date'])->toBe('2026-09-01')
        ->and($result['summary']['last_shift_date'])->toBe('2026-09-30')
        ->and($result['summary']['total_scheduled_minutes'])->toBe(14400)
        ->and($result['summary']['can_determine_full_coverage'])->toBeFalse()
        ->and($result['summary']['required_start_time'])->toBeNull()
        ->and($result['summary']['required_end_time'])->toBeNull()
        ->and($result['summary']['days_without_shifts'])->toBe([])
        ->and($result['summary']['fully_covered'])->toBeNull()
        ->and($result['summary']['daily_coverage'])->toHaveCount(30);

    $coverage = \readToolResult($tool, [
        'request' => [
            'operation' => 'summary',
            'store_id' => $store->getKey(),
            'year' => 2026,
            'month' => 9,
            'required_start_time' => '08:00',
            'required_end_time' => '16:00',
        ],
    ]);

    \expect($coverage['summary']['can_determine_full_coverage'])->toBeTrue()
        ->and($coverage['summary']['fully_covered'])->toBeTrue()
        ->and($coverage['summary']['days_without_full_coverage'])->toBe([])
        ->and($coverage['summary']['daily_coverage'][0]['scheduled_intervals'])->toBe([
            ['start_time' => '08:00', 'end_time' => '16:00'],
        ]);
});

/**
 * @param array<string, mixed> $arguments
 *
 * @return array<string, mixed>
 */
function readToolResult(Tool $tool, array $arguments): array
{
    return \json_decode(
        $tool->handle(new Request($arguments, 'read-call')),
        true,
        512,
        \JSON_THROW_ON_ERROR,
    );
}
