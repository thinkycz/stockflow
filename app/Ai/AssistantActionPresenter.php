<?php

declare(strict_types=1);

namespace App\Ai;

use InvalidArgumentException;
use Thinkycz\LaravelCore\Support\Typer;

final class AssistantActionPresenter
{
    private const array SPECIAL_ACTIONS = [
        'create_worker',
        'update_worker',
        'delete_worker',
        'create_shift',
        'quick_add_shift',
        'update_shift',
        'delete_shift',
        'create_shift_preset',
        'update_shift_preset',
        'delete_shift_preset',
        'create_stock_movement',
        'reverse_stock_movement',
    ];

    private const array SAFE_SUMMARY_KEYS = [
        'first_name',
        'last_name',
        'hourly_rate',
        'hours',
        'name',
        'email',
        'title',
        'label',
        'date',
        'start_time',
        'end_time',
        'amount',
        'quantity',
        'counted_on',
        'public_name',
        'year',
        'month',
        'effective_period',
        'ends_before_period',
        'direction',
        'occurred_on',
        'expires_on',
        'due_day',
        'locked',
        'archived',
        'scope',
        'shift',
        'type',
        'completed',
    ];

    /**
     * Resolve the required localized summary key for a declared writer action.
     */
    public function summaryKey(string $action): string
    {
        if (!\in_array($action, $this->actions(), true)) {
            throw new InvalidArgumentException('Unknown assistant action presentation.');
        }

        return 'assistant.action_summaries.' . $action;
    }

    /**
     * Convert a validated writer preview into the non-technical public contract.
     *
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $validatedPreview
     *
     * @return array<string, mixed>
     */
    public function present(array $arguments, array $validatedPreview): array
    {
        $request = Typer::assertStringKeyArray(Typer::assertArray($arguments['request'] ?? null));
        $action = Typer::assertString($request['action'] ?? null);
        $values = \is_array($request['values'] ?? null)
            ? Typer::assertStringKeyArray($request['values'])
            : [];
        $context = \is_array($request['context'] ?? null)
            ? Typer::assertStringKeyArray($request['context'])
            : [];
        $store = $validatedPreview['store'] ?? null;
        $params = [];

        foreach ([...$context, ...$values] as $key => $value) {
            if (\in_array($key, self::SAFE_SUMMARY_KEYS, true) && \is_scalar($value)) {
                $params[$key] = \is_string($value) ? \mb_substr($value, 0, 160) : $value;
            }
        }

        if (\is_array($store) && \is_string($store['name'] ?? null)) {
            $params['store'] = \mb_substr($store['name'], 0, 160);
        }

        if (\is_string($request['mode'] ?? null)) {
            $params['mode'] = \mb_substr($request['mode'], 0, 80);
        }

        if (\is_array($validatedPreview['summary_params'] ?? null)) {
            foreach ($validatedPreview['summary_params'] as $key => $value) {
                if (\is_string($key) && \is_scalar($value)) {
                    $params[$key] = \is_string($value) ? \mb_substr($value, 0, 160) : $value;
                }
            }
        }

        foreach (['items', 'rows', 'days', 'tasks', 'answers', 'variants', 'tokens'] as $collection) {
            if (\is_array($values[$collection] ?? null)) {
                $params[$collection . '_count'] = \count($values[$collection]);
            }
        }

        return [
            'version' => 2,
            'kind' => 'action_confirmation',
            'summary_key' => $this->summaryKey($action),
            'summary_params' => $params,
            ...\array_filter([
                'business_rows' => \is_array($validatedPreview['business_rows'] ?? null)
                    ? $this->providedBusinessRows($validatedPreview['business_rows'])
                    : $this->businessRows($values),
            ], static fn(array $rows): bool => $rows !== []),
        ];
    }

    /**
     * @return list<string>
     */
    private function actions(): array
    {
        $actions = self::SPECIAL_ACTIONS;

        foreach (AssistantResourceToolDefinitions::writers() as $definition) {
            $actions = [...$actions, ...\array_keys($definition['actions'])];
        }

        return \array_values(\array_unique($actions));
    }

    /**
     * Build a bounded, read-only summary for fixed mutation rows.
     *
     * @param array<string, mixed> $values
     *
     * @return list<array{label: string, value: string|null}>
     */
    private function businessRows(array $values): array
    {
        foreach (['items', 'rows', 'days', 'tasks', 'answers'] as $key) {
            if (!\is_array($values[$key] ?? null)) {
                continue;
            }

            $rows = [];
            $position = 1;

            foreach (\array_slice($values[$key], 0, 50) as $row) {
                if (!\is_array($row)) {
                    continue;
                }

                $row = Typer::assertStringKeyArray($row);
                $label = $this->firstSafeString($row, ['name', 'title', 'label', 'text', 'date', 'ingredient_name'])
                    ?? (string) $position . '.';
                $value = $this->firstSafeScalar($row, ['quantity', 'quantity_after', 'amount', 'start_time', 'end_time']);
                $rows[] = [
                    'label' => \mb_substr($label, 0, 160),
                    'value' => $value === null ? null : \mb_substr((string) $value, 0, 160),
                ];
                ++$position;
            }

            return $rows;
        }

        return [];
    }

    /**
     * @param array<array-key, mixed> $rows
     *
     * @return list<array{label: string, value: string|null}>
     */
    private function providedBusinessRows(array $rows): array
    {
        $result = [];

        foreach (\array_slice($rows, 0, 50) as $row) {
            if (!\is_array($row) || !\is_string($row['label'] ?? null)) {
                continue;
            }

            $value = $row['value'] ?? null;

            if ($value !== null && !\is_scalar($value)) {
                continue;
            }

            $result[] = [
                'label' => \mb_substr($row['label'], 0, 160),
                'value' => $value === null ? null : \mb_substr((string) $value, 0, 160),
            ];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $keys
     */
    private function firstSafeString(array $row, array $keys): string|null
    {
        foreach ($keys as $key) {
            if (\is_string($row[$key] ?? null) && $row[$key] !== '') {
                return $row[$key];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $keys
     */
    private function firstSafeScalar(array $row, array $keys): bool|float|int|string|null
    {
        foreach ($keys as $key) {
            if (\is_scalar($row[$key] ?? null)) {
                return $row[$key];
            }
        }

        return null;
    }
}
