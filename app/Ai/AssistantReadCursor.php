<?php

declare(strict_types=1);

namespace App\Ai;

use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;
use JsonException;
use Thinkycz\LaravelCore\Support\Typer;

final class AssistantReadCursor
{
    private const int VERSION = 2;

    private const int TTL_MINUTES = 30;

    /**
     * @param array<string, mixed> $filters
     * @param array<string, int|string|null> $after
     */
    public function encode(User $actor, string $resource, string $dataset, array $filters, array $after, string $asOf): string
    {
        return Crypt::encryptString(\json_encode([
            'version' => self::VERSION,
            'actor_id' => $actor->resolveScopeUser()->getKey(),
            'resource' => $resource,
            'dataset' => $dataset,
            'filters_hash' => $this->filtersHash($filters),
            'after' => $after,
            'as_of' => $asOf,
            'expires_at' => Carbon::now()->addMinutes(self::TTL_MINUTES)->toJSON(),
        ], \JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{after: array<string, int|string|null>, as_of: string}
     */
    public function decode(User $actor, string $resource, string $dataset, array $filters, string $cursor): array
    {
        try {
            $decoded = \json_decode(Crypt::decryptString($cursor), true, 32, \JSON_THROW_ON_ERROR);
            if (!\is_array($decoded)) {
                throw new InvalidArgumentException('The read cursor is invalid.');
            }
            $payload = Typer::assertStringKeyArray($decoded);
        } catch (DecryptException|JsonException) {
            throw new InvalidArgumentException('The read cursor is invalid.');
        }

        if (
            Typer::parseInt($payload['version'] ?? null) !== self::VERSION ||
            Typer::parseInt($payload['actor_id'] ?? null) !== $actor->resolveScopeUser()->getKey() ||
            $resource !== Typer::parseNullableString($payload['resource'] ?? null) ||
            $dataset !== Typer::parseNullableString($payload['dataset'] ?? null) ||
            Typer::parseNullableString($payload['filters_hash'] ?? null) !== $this->filtersHash($filters) ||
            Carbon::parse(Typer::assertString($payload['expires_at'] ?? null))->isPast()
        ) {
            throw new InvalidArgumentException('The read cursor does not match this query.');
        }

        $after = Typer::assertStringKeyArray(Typer::assertArray($payload['after'] ?? []));
        $normalizedAfter = [];
        foreach ($after as $key => $value) {
            if (!\is_int($value) && !\is_string($value) && $value !== null) {
                throw new InvalidArgumentException('The read cursor contains an invalid sort key.');
            }
            $normalizedAfter[$key] = $value;
        }

        return ['after' => $normalizedAfter, 'as_of' => Typer::assertString($payload['as_of'] ?? null)];
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function filtersHash(array $filters): string
    {
        unset($filters['cursor'], $filters['limit']);
        $this->sort($filters);

        return \hash('sha256', \json_encode($filters, \JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, mixed> $values
     */
    private function sort(array &$values): void
    {
        \ksort($values);
        foreach ($values as &$value) {
            if (\is_array($value) && !\array_is_list($value)) {
                $nested = Typer::assertStringKeyArray($value);
                $this->sort($nested);
                $value = $nested;
            }
        }
    }
}
