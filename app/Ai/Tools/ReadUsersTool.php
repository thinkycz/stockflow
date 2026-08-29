<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class ReadUsersTool extends AbstractReadResourceTool
{
    /**
     * Stable provider-facing tool name.
     */
    public function name(): string { return 'read_users'; }

    /**
     * Explain the safely exposed user facts available to the model.
     */
    public function description(): string { return 'Read the administrator and managed users with email, verification, locale, assigned store, and enabled application sections. Credentials are never returned.'; }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $filters = ['search' => $schema->string(), 'store_id' => $schema->integer(), 'verified' => $schema->boolean(), 'role' => $schema->string()->enum(['admin', 'limited'])];

        return ['request' => $schema->anyOf([
            $schema->object(['operation' => $schema->string()->enum(['list'])->required(), ...$filters, 'limit' => $schema->integer()->min(1)->max(50), 'cursor' => $schema->string()])->withoutAdditionalProperties(),
            $schema->object(['operation' => $schema->string()->enum(['detail'])->required(), 'id' => $schema->integer()->required()])->withoutAdditionalProperties(),
            $schema->object(['operation' => $schema->string()->enum(['summary'])->required(), ...$filters])->withoutAdditionalProperties(),
        ])->required()];
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    protected function execute(array $request): array
    {
        $operation = Typer::parseNullableString($request['operation'] ?? null) ?? 'list';
        if ($operation === 'detail') {
            return $this->detailResult($request, 'users', $this->record($this->user(Typer::parseNullableInt($request['id'] ?? null))));
        }
        $query = User::query();
        User::scopeForAdmin($query, $this->actor->resolveScopeUser());
        $this->filters($query, $request);
        if ($operation === 'summary') {
            $users = $query->get();

            return $this->summaryResult($request, 'users', [
                'user_count' => $users->count(),
                'admin_count' => $users->filter(static fn(User $user): bool => $user->isAdmin())->count(),
                'limited_count' => $users->filter(static fn(User $user): bool => !$user->isAdmin())->count(),
                'verified_count' => $users->filter(static fn(User $user): bool => $user->getEmailVerifiedAt() !== null)->count(),
                'unverified_count' => $users->filter(static fn(User $user): bool => $user->getEmailVerifiedAt() === null)->count(),
            ], $users->isEmpty() ? 'NO_MATCHING_DATA' : null);
        }
        if ($operation !== 'list') { throw new InvalidArgumentException('Unknown user read operation.'); }

        return $this->paginateById($query, $request, 'users', $request, fn(User $user): array => $this->record($user));
    }

    /**
     * Resource identifier used by cursors, envelopes, and audits.
     */
    protected function resource(): string { return 'users'; }

    /**
     * Resolve one user visible to the main administrator.
     */
    private function user(int|null $id): User
    {
        if ($id === null) { throw new InvalidArgumentException('A user identifier is required.'); }
        $query = User::query();
        User::scopeForAdmin($query, $this->actor->resolveScopeUser());

        return $query->findOrFail($id);
    }

    /**
     * @param Builder<User> $query
     * @param array<string, mixed> $request
     */
    private function filters(Builder $query, array $request): void
    {
        $search = Typer::parseNullableString($request['search'] ?? null);
        if ($search !== null && \mb_trim($search) !== '') { $query->where('email', 'like', '%' . \mb_trim($search) . '%'); }
        $storeId = Typer::parseNullableInt($request['store_id'] ?? null);
        if ($storeId !== null) { $this->ownedStore($storeId);
            $query->where('assigned_store_id', $storeId); }
        if (\array_key_exists('verified', $request)) { (bool) $request['verified'] ? $query->whereNotNull('email_verified_at') : $query->whereNull('email_verified_at'); }
        $role = Typer::parseNullableString($request['role'] ?? null);
        if ($role === 'admin') { User::scopeAdmin($query); }
        if ($role === 'limited') { User::scopeLimited($query); }
    }

    /**
     * @return array<string, mixed>
     */
    private function record(User $user): array
    {
        $store = $user->getAssignedStore();

        return [
            'id' => $user->getKey(), 'email' => $user->getEmail(), 'role' => $user->isAdmin() ? 'admin' : 'limited',
            'locale' => $user->getLocale(), 'email_verified' => $user->getEmailVerifiedAt() !== null,
            'assigned_store_id' => $user->getAssignedStoreId(), 'assigned_store_name' => $store?->getName(),
            'enabled_sections' => $user->getEnabledSectionValues(),
            'url' => $user->isAdmin() ? Resolver::resolveUrlGenerator()->route('settings.show') : Resolver::resolveUrlGenerator()->route('users.edit', $user->getKey()),
        ];
    }
}
