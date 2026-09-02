<?php

declare(strict_types=1);

namespace App\Ai\Operations\Administration;

use App\Ai\Operations\AssistantOperationExecutor;
use App\Enums\RemovalOutcomeEnum;
use App\Http\Validation\ItemValidity;
use App\Http\Validation\StoreValidity;
use App\Http\Validation\UserValidity;
use App\Http\Validation\WorkerValidity;
use App\Models\Item;
use App\Models\OperationalDailyDigest;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use App\Services\AdministrationManagementService;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;
use Thinkycz\LaravelCore\Validation\AuthValidity;

final class AdministrationOperationExecutor implements AssistantOperationExecutor
{
    /**
     * Create the shared administration executor.
     */
    public function __construct(private readonly AdministrationManagementService $administration) {}

    /**
     * Validate a proposal and resolve all locked ownership context.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    public function preview(string $identifier, User $actor, array $arguments): array
    {
        $this->assertAdmin($actor);
        $storeId = Typer::parseNullableInt($arguments['store_id'] ?? null);
        $targetId = Typer::parseNullableInt($arguments['target_id'] ?? null);
        $store = $storeId === null ? null : $this->store($actor, $storeId);
        $context = $this->json($arguments, 'context_json');
        $values = $this->json($arguments, 'values_json');
        $this->validate($identifier, $actor, $store, $targetId, $context, $values);
        $targetType = $this->resolveTarget($identifier, $actor, $store, $targetId);

        return [
            'operation' => $identifier,
            'store' => $store === null ? null : ['id' => $store->getKey(), 'name' => $store->getName()],
            'target' => $targetId === null ? null : ['type' => $targetType, 'id' => (string) $targetId],
            'effects' => $this->effects($identifier),
            'sanitized_arguments' => ['context' => $context, 'values' => $values],
            'safe_editable_fields' => ['values_json'],
        ];
    }

    /**
     * Execute an approved administration action through the shared service.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    public function execute(string $identifier, User $actor, array $arguments): array
    {
        $this->assertAdmin($actor);
        $storeId = Typer::parseNullableInt($arguments['store_id'] ?? null);
        $targetId = Typer::parseNullableInt($arguments['target_id'] ?? null);
        $store = $storeId === null ? null : $this->store($actor, $storeId);
        $context = $this->json($arguments, 'context_json');
        $values = $this->json($arguments, 'values_json');
        $this->validate($identifier, $actor, $store, $targetId, $context, $values);
        $this->resolveTarget($identifier, $actor, $store, $targetId);
        $result = $this->run($identifier, $actor, $store, $targetId, $values);
        $recordId = \is_int($result) ? $result : $result['id'];

        return [
            'operation' => $identifier,
            'status' => 'succeeded',
            'record' => [
                'type' => $this->resultType($identifier),
                'id' => $recordId,
                'store_id' => $store?->getKey(),
                'url' => $this->url($identifier, $recordId),
                ...(\is_array($result) ? ['removal_outcome' => $result['removal_outcome']] : []),
            ],
        ];
    }

    /**
     * Execute one fixed administration operation.
     *
     * @param array<string, mixed> $values
     *
     * @return array{id: int, removal_outcome: string}|int
     */
    private function run(string $identifier, User $actor, Store|null $store, int|null $targetId, array $values): array|int
    {
        if ($identifier === 'create_item') {
            return $this->administration->createItem(
                $actor,
                Typer::assertString($values['title'] ?? null),
                Typer::parseNullableString($values['sku'] ?? null),
                Typer::parseNullableString($values['unit'] ?? null),
                $this->decimalString($values['purchase_price'] ?? null),
                Typer::parseNullableString($values['description'] ?? null),
            )->getKey();
        }

        if ($identifier === 'update_item') {
            return $this->administration->updateItem(
                $actor,
                $this->item($actor, Typer::assertInt($targetId)),
                Typer::assertString($values['title'] ?? null),
                Typer::parseNullableString($values['sku'] ?? null),
                Typer::parseNullableString($values['unit'] ?? null),
                $this->decimalString($values['purchase_price'] ?? null),
                Typer::parseNullableString($values['description'] ?? null),
            )->getKey();
        }

        if ($identifier === 'delete_item') {
            $item = $this->item($actor, Typer::assertInt($targetId));
            $this->administration->deleteItem($actor, $item);

            return $item->getKey();
        }

        if ($identifier === 'create_store') {
            return $this->administration->createStore(
                $actor,
                Typer::assertString($values['name'] ?? null),
                Typer::parseNullableString($values['address'] ?? null),
                Typer::assertString($values['status'] ?? null),
                Typer::parseNullableString($values['notes'] ?? null),
                $this->channel($values['slack_channel'] ?? null),
                Typer::parseBool($values['is_warehouse'] ?? false),
            )->getKey();
        }

        if ($identifier === 'update_store') {
            return $this->administration->updateStore(
                $actor,
                Typer::assertInstance($store, Store::class),
                Typer::assertString($values['name'] ?? null),
                Typer::parseNullableString($values['address'] ?? null),
                Typer::assertString($values['status'] ?? null),
                Typer::parseNullableString($values['notes'] ?? null),
                $this->channel($values['slack_channel'] ?? null),
                Typer::parseBool($values['is_warehouse'] ?? false),
            )->getKey();
        }

        if ($identifier === 'delete_store') {
            $resolvedStore = Typer::assertInstance($store, Store::class);
            $outcome = $this->administration->deleteStore($actor, $resolvedStore);
            if ($outcome === RemovalOutcomeEnum::BLOCKED) {
                throw new RuntimeException('Resolve store assignments, stock, and active operational work before removing this store.');
            }

            return ['id' => $resolvedStore->getKey(), 'removal_outcome' => $outcome->value];
        }

        if ($identifier === 'switch_active_store') {
            $resolvedStore = Typer::assertInstance($store, Store::class);
            $this->administration->switchStore($actor, $resolvedStore);

            return $resolvedStore->getKey();
        }

        if ($identifier === 'create_user') {
            return $this->administration->createUser(
                $actor,
                Typer::assertString($values['email'] ?? null),
                Str::random(64),
                Typer::assertInstance($store, Store::class),
            )->getKey();
        }

        if ($identifier === 'update_user') {
            return $this->administration->updateUser(
                $actor,
                $this->limitedUser($actor, Typer::assertInt($targetId)),
                Typer::assertString($values['email'] ?? null),
                null,
                Typer::assertInstance($store, Store::class),
                null,
            )->getKey();
        }

        if ($identifier === 'delete_user') {
            $user = $this->limitedUser($actor, Typer::assertInt($targetId));

            if (!$this->administration->deleteUser($actor, $user)) {
                throw new RuntimeException('The selected main administrator cannot be deleted.');
            }

            return $user->getKey();
        }

        if ($identifier === 'create_worker') {
            return $this->administration->createWorker(
                $actor,
                Typer::assertString($values['first_name'] ?? null),
                Typer::assertString($values['last_name'] ?? null),
                Typer::parseFloat($values['hourly_rate'] ?? null),
                Typer::parseNullableString($values['calendar_color'] ?? null),
                Typer::parseBool($values['attendance_rating_enabled'] ?? true),
            )->getKey();
        }

        if ($identifier === 'update_worker') {
            return $this->administration->updateWorker(
                $actor,
                $this->worker($actor, Typer::assertInt($targetId)),
                Typer::assertString($values['first_name'] ?? null),
                Typer::assertString($values['last_name'] ?? null),
                Typer::parseFloat($values['hourly_rate'] ?? null),
                Typer::parseNullableString($values['calendar_color'] ?? null),
                Typer::parseBool($values['attendance_rating_enabled'] ?? true),
            )->getKey();
        }

        if ($identifier === 'delete_worker') {
            $worker = $this->worker($actor, Typer::assertInt($targetId));

            $outcome = $this->administration->deleteWorker($actor, $worker);
            if ($outcome === RemovalOutcomeEnum::BLOCKED) {
                throw new RuntimeException('Resolve active attendance and future worker scheduling before removing this worker.');
            }

            return ['id' => $worker->getKey(), 'removal_outcome' => $outcome->value];
        }

        if ($identifier === 'restore_worker') {
            return $this->administration->restoreWorker(
                $actor,
                $this->worker($actor, Typer::assertInt($targetId)),
            )->getKey();
        }

        if ($identifier === 'update_profile') {
            return $this->administration->updateProfile(
                $actor,
                Typer::assertString($values['email'] ?? null),
                Typer::assertString($values['locale'] ?? null),
            )->getKey();
        }

        if ($identifier === 'update_slack_channel') {
            return $this->administration->updateSlackChannel($actor, $this->channel($values['company_slack_channel'] ?? null))->getKey();
        }

        if ($identifier === 'test_slack_channel') {
            if (!$this->administration->testSlackChannel($actor)) {
                throw new RuntimeException('Configure a Slack channel before sending a test notification.');
            }

            return $actor->getKey();
        }

        if ($identifier === 'retry_slack_digest') {
            $digest = $this->digest($actor, Typer::assertInt($targetId));
            $this->administration->retrySlackDigest($actor, $digest);

            return $digest->getKey();
        }

        throw new InvalidArgumentException('Unknown administration operation.');
    }

    /**
     * Normalize a validated JSON decimal without requiring the model to quote it.
     */
    private function decimalString(mixed $value): string
    {
        if (\is_int($value) || \is_float($value)) {
            return (string) $value;
        }

        return Typer::assertString($value);
    }

    /**
     * Validate values with the same validity classes as the web forms.
     *
     * @param array<string, mixed> $context
     * @param array<string, mixed> $values
     */
    private function validate(string $identifier, User $actor, Store|null $store, int|null $targetId, array $context, array $values): void
    {
        $item = ItemValidity::inject($actor->getKey());
        $storeValidity = StoreValidity::inject($actor->getKey());
        $user = UserValidity::inject($actor->getKey());
        $worker = WorkerValidity::inject($actor->getKey());
        $payload = [...$context, ...$values];
        $rules = match ($identifier) {
            'create_item', 'update_item' => [
                'title' => $item->title()->required()->toArray(),
                'sku' => $item->sku($identifier === 'update_item' ? $targetId : null)->nullable()->toArray(),
                'unit' => $item->unit()->nullable()->toArray(),
                'purchase_price' => $item->purchasePrice()->required()->toArray(),
                'description' => $item->description()->nullable()->toArray(),
            ],
            'delete_item', 'delete_store', 'switch_active_store', 'delete_user', 'delete_worker', 'restore_worker', 'test_slack_channel', 'retry_slack_digest' => [],
            'create_store', 'update_store' => [
                'name' => $storeValidity->name()->required()->toArray(),
                'address' => $storeValidity->address()->nullable()->toArray(),
                'status' => $storeValidity->status()->required()->toArray(),
                'notes' => $storeValidity->notes()->nullable()->toArray(),
                'slack_channel' => $storeValidity->slackChannel()->nullable()->toArray(),
                'is_warehouse' => $storeValidity->isWarehouse()->nullable()->toArray(),
            ],
            'create_user' => ['email' => $user->email()->required()->toArray()],
            'update_user' => ['email' => $user->email($targetId)->required()->toArray()],
            'create_worker', 'update_worker' => [
                'first_name' => $worker->firstName()->required()->toArray(),
                'last_name' => $worker->lastName()->required()->toArray(),
                'hourly_rate' => $worker->hourlyRate()->required()->toArray(),
                'attendance_rating_enabled' => $worker->attendanceRatingEnabled()->nullable()->toArray(),
                'calendar_color' => $worker->calendarColor()->nullable()->toArray(),
            ],
            'update_profile' => [
                'email' => AuthValidity::inject()->email()->unique('users', 'email', $actor->getKey())->required()->toArray(),
                'locale' => AuthValidity::inject()->locale()->required()->toArray(),
            ],
            'update_slack_channel' => [
                'company_slack_channel' => $storeValidity->slackChannel()->nullable()->toArray(),
            ],
            default => throw new InvalidArgumentException('Unknown administration operation.'),
        };

        Resolver::resolveValidatorFactory()->make($payload, $rules)->validate();

        if (\in_array($identifier, ['create_user', 'update_user'], true) && !$store instanceof Store) {
            throw new InvalidArgumentException('A locked assigned store is required.');
        }
    }

    /**
     * Resolve and authorize an operation target.
     */
    private function resolveTarget(string $identifier, User $actor, Store|null $store, int|null $targetId): string|null
    {
        return match ($identifier) {
            'update_item', 'delete_item' => $this->resolvedItem($actor, Typer::assertInt($targetId)),
            'update_store', 'delete_store', 'switch_active_store' => $this->resolvedStoreTarget($store, $targetId),
            'update_user', 'delete_user' => $this->resolvedUser($actor, Typer::assertInt($targetId)),
            'update_worker', 'delete_worker', 'restore_worker' => $this->resolvedWorker($actor, Typer::assertInt($targetId)),
            'retry_slack_digest' => $this->resolvedDigest($actor, Typer::assertInt($targetId)),
            'create_item', 'create_store', 'create_user', 'create_worker', 'update_profile', 'update_slack_channel', 'test_slack_channel' => null,
            default => throw new InvalidArgumentException('Unknown administration operation.'),
        };
    }

    /**
     * Resolve an owned item target type.
     */
    private function resolvedItem(User $actor, int $targetId): string
    {
        $this->item($actor, $targetId);

        return 'item';
    }

    /**
     * Resolve a locked store target type and require matching target/store IDs.
     */
    private function resolvedStoreTarget(Store|null $store, int|null $targetId): string
    {
        $resolvedStore = Typer::assertInstance($store, Store::class);

        if ($resolvedStore->getKey() !== Typer::assertInt($targetId)) {
            throw new InvalidArgumentException('The locked store and target IDs must match.');
        }

        return 'store';
    }

    /**
     * Resolve a managed limited-user target type.
     */
    private function resolvedUser(User $actor, int $targetId): string
    {
        $this->limitedUser($actor, $targetId);

        return 'user';
    }

    /**
     * Resolve an owned worker target type.
     */
    private function resolvedWorker(User $actor, int $targetId): string
    {
        $this->worker($actor, $targetId);

        return 'worker';
    }

    /**
     * Resolve an owned Slack digest target type.
     */
    private function resolvedDigest(User $actor, int $targetId): string
    {
        $this->digest($actor, $targetId);

        return 'operational_daily_digest';
    }

    /**
     * Resolve an owned store.
     */
    private function store(User $actor, int $id): Store
    {
        return Typer::assertInstance(Store::query()->where('user_id', $actor->getKey())->whereKey($id)->firstOrFail(), Store::class);
    }

    /**
     * Resolve an owned item.
     */
    private function item(User $actor, int $id): Item
    {
        return Typer::assertInstance(Item::query()->where('user_id', $actor->getKey())->whereKey($id)->firstOrFail(), Item::class);
    }

    /**
     * Resolve a limited user managed by the main admin.
     */
    private function limitedUser(User $actor, int $id): User
    {
        $query = User::query();
        User::scopeLimited($query);

        return Typer::assertInstance($query->where('parent_user_id', $actor->getKey())->whereKey($id)->firstOrFail(), User::class);
    }

    /**
     * Resolve an owned worker.
     */
    private function worker(User $actor, int $id): Worker
    {
        return Typer::assertInstance(Worker::query()->where('user_id', $actor->getKey())->whereKey($id)->firstOrFail(), Worker::class);
    }

    /**
     * Resolve a company Slack digest.
     */
    private function digest(User $actor, int $id): OperationalDailyDigest
    {
        return Typer::assertInstance(OperationalDailyDigest::query()
            ->where('company_user_id', $actor->getKey())
            ->whereKey($id)
            ->firstOrFail(), OperationalDailyDigest::class);
    }

    /**
     * Normalize an optional Slack channel exactly like the settings forms.
     */
    private function channel(mixed $value): string|null
    {
        $channel = \mb_trim(Typer::parseNullableString($value) ?? '');

        return $channel === '' ? null : $channel;
    }

    /**
     * Ensure only the main administrator can invoke administration tools.
     */
    private function assertAdmin(User $actor): void
    {
        if (!$actor->isAdmin()) {
            \abort(403);
        }
    }

    /**
     * Describe the exact expected business effect.
     */
    private function effects(string $identifier): string
    {
        return match ($identifier) {
            'create_item' => 'Creates the catalog item and its zero-quantity warehouse stock row transactionally.',
            'update_item' => 'Updates item metadata without changing inventory quantities.',
            'delete_item' => 'Deletes an unreferenced item, its store rows, and draft count rows transactionally.',
            'create_store' => 'Creates the store and initializes checklist records for a retail store.',
            'update_store' => 'Updates the selected store metadata and Slack destination.',
            'delete_store' => 'Deletes a pristine store, deactivates a historical store, and blocks stores with live operational work.',
            'switch_active_store' => 'Persists the selected active store for the current browser session.',
            'create_user' => 'Creates a limited account with a server-generated secret and the selected store assignment.',
            'update_user' => 'Updates the limited account email and assigned store without handling a password.',
            'delete_user' => 'Deletes the selected limited account.',
            'create_worker' => 'Creates a worker used by shifts, attendance, recipes, and payroll.',
            'update_worker' => 'Updates the selected worker profile and wage rate.',
            'delete_worker' => 'Deletes a pristine worker, archives historical workers, and blocks workers with active attendance or future scheduling.',
            'restore_worker' => 'Restores an archived worker to active scheduling and operational selectors.',
            'update_profile' => 'Updates the main admin email and locale.',
            'update_slack_channel' => 'Updates the company-wide Slack destination.',
            'test_slack_channel' => 'Sends the normal test notification to the configured Slack destination.',
            'retry_slack_digest' => 'Requeues the selected failed daily Slack digest.',
            default => throw new InvalidArgumentException('Unknown administration operation.'),
        };
    }

    /**
     * Resolve the result record type.
     */
    private function resultType(string $identifier): string
    {
        return match (true) {
            \str_contains($identifier, 'item') => 'item',
            \str_contains($identifier, 'store') => 'store',
            \str_contains($identifier, 'user') => 'user',
            \str_contains($identifier, 'worker') => 'worker',
            \str_contains($identifier, 'digest') => 'operational_daily_digest',
            default => 'user_profile',
        };
    }

    /**
     * Build the normal application link for an operation result.
     */
    private function url(string $identifier, int $recordId): string
    {
        return match (true) {
            $identifier === 'create_item', $identifier === 'update_item' => Resolver::resolveUrlGenerator()->route('items.show', $recordId),
            \str_contains($identifier, 'item') => Resolver::resolveUrlGenerator()->route('items.index'),
            $identifier === 'create_store', $identifier === 'update_store' => Resolver::resolveUrlGenerator()->route('stores.show', $recordId),
            \str_contains($identifier, 'store') => Resolver::resolveUrlGenerator()->route('stores.index'),
            \str_contains($identifier, 'worker') => Resolver::resolveUrlGenerator()->route('workers.index'),
            \str_contains($identifier, 'user') => Resolver::resolveUrlGenerator()->route('users.index'),
            $identifier === 'retry_slack_digest' => Resolver::resolveUrlGenerator()->route('settings.slack-digests.show', ['digest' => $recordId]),
            default => Resolver::resolveUrlGenerator()->route('settings.show'),
        };
    }

    /**
     * Decode a bounded JSON object from the mutation envelope.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private function json(array $arguments, string $key): array
    {
        $json = Typer::parseNullableString($arguments[$key] ?? null) ?? '{}';

        if (\mb_strlen($json) > 50_000) {
            throw new InvalidArgumentException('Assistant operation arguments are too large.');
        }

        return Typer::assertStringKeyArray(Typer::assertArray(\json_decode($json, true, 32, \JSON_THROW_ON_ERROR)));
    }
}
