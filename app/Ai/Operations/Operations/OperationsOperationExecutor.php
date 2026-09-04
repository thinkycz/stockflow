<?php

declare(strict_types=1);

namespace App\Ai\Operations\Operations;

use App\Ai\Operations\AssistantOperationExecutor;
use App\Domain\Checklists\ChecklistService;
use App\Domain\Noticeboard\NoticeboardCardService;
use App\Enums\ChecklistShiftEnum;
use App\Enums\ChecklistTemplateScopeEnum;
use App\Enums\NoticeboardCardSizeEnum;
use App\Http\Validation\ChecklistValidity;
use App\Http\Validation\NoticeboardCardValidity;
use App\Models\ChecklistDay;
use App\Models\ChecklistItem;
use App\Models\NoticeboardCard;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use InvalidArgumentException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;

final class OperationsOperationExecutor implements AssistantOperationExecutor
{
    /**
     * Create the service-backed daily-operations executor.
     */
    public function __construct(
        private readonly ChecklistService $checklists,
        private readonly NoticeboardCardService $noticeboard,
    ) {}

    /**
     * Validate the proposal and resolve its exact store and record target.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    public function preview(string $identifier, User $actor, array $arguments): array
    {
        $store = $this->store($actor, Typer::assertInt(Typer::parseNullableInt($arguments['store_id'] ?? null)));
        $targetId = Typer::parseNullableInt($arguments['target_id'] ?? null);
        $context = $this->json($arguments, 'context_json');
        $values = $this->json($arguments, 'values_json');
        $this->validate($identifier, $actor, $context, $values);
        $targetType = $this->resolveTarget($identifier, $actor, $store, $targetId);

        return [
            'operation' => $identifier,
            'store' => ['id' => $store->getKey(), 'name' => $store->getName()],
            'target' => $targetId === null ? null : ['type' => $targetType, 'id' => (string) $targetId],
            'effects' => $this->effects($identifier),
            'sanitized_arguments' => ['context' => $context, 'values' => $values],
            'safe_editable_fields' => ['values_json'],
        ];
    }

    /**
     * Execute an approved checklist or noticeboard action through its normal service.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    public function execute(string $identifier, User $actor, array $arguments): array
    {
        $store = $this->store($actor, Typer::assertInt(Typer::parseNullableInt($arguments['store_id'] ?? null)));
        $targetId = Typer::parseNullableInt($arguments['target_id'] ?? null);
        $context = $this->json($arguments, 'context_json');
        $values = $this->json($arguments, 'values_json');
        $this->validate($identifier, $actor, $context, $values);
        $this->resolveTarget($identifier, $actor, $store, $targetId);
        $resultId = $this->run($identifier, $actor, $store, $targetId, $context, $values);

        return [
            'operation' => $identifier,
            'status' => 'succeeded',
            'record' => [
                'type' => \str_contains($identifier, 'noticeboard') ? 'noticeboard_card' : 'checklist',
                'id' => $resultId ?? $targetId,
                'store_id' => $store->getKey(),
                'url' => Resolver::resolveUrlGenerator()->route(
                    \str_contains($identifier, 'noticeboard') ? 'dashboard' : 'checklists.index',
                ),
            ],
        ];
    }

    /**
     * Execute one fixed daily-operations action.
     *
     * @param array<string, mixed> $context
     * @param array<string, mixed> $values
     */
    private function run(string $identifier, User $actor, Store $store, int|null $targetId, array $context, array $values): int|null
    {
        if ($identifier === 'update_checklist_item') {
            $item = $this->checklistItem($actor, $store, Typer::assertInt($targetId));
            $workerId = Typer::parseNullableInt($context['worker_id'] ?? null);
            $this->checklists->updateItem(
                $item,
                $store,
                $actor,
                $workerId === null ? null : $this->worker($actor, $workerId),
                Typer::parseBool($values['completed'] ?? null),
                Typer::parseInt($context['lock_version'] ?? null),
            );

            return $item->getKey();
        }

        if (\in_array($identifier, ['excuse_checklist_day', 'restore_checklist_day'], true)) {
            $day = $this->checklistDay($actor, $store, Typer::assertInt($targetId));
            $this->checklists->excuseDay($day, $actor, Typer::assertString($values['reason'] ?? null), $identifier === 'excuse_checklist_day');

            return $day->getKey();
        }

        if ($identifier === 'replace_checklist_template') {
            $texts = [];

            foreach (Typer::assertArray($values['tasks'] ?? null) as $row) {
                $texts[] = \mb_trim(Typer::assertString(Typer::assertStringKeyArray(Typer::assertArray($row))['text'] ?? null));
            }

            $this->checklists->replaceTemplateGroup(
                $actor,
                $store,
                ChecklistTemplateScopeEnum::from(Typer::assertString($values['scope'] ?? null)),
                Typer::parseNullableInt($values['weekday'] ?? null),
                ChecklistShiftEnum::from(Typer::assertString($values['shift'] ?? null)),
                $texts,
            );

            return null;
        }

        if ($identifier === 'create_noticeboard_card') {
            try {
                return $this->noticeboard->create(
                    $actor,
                    $store,
                    Typer::assertString($values['body_html'] ?? null),
                    Typer::assertString($values['label'] ?? null),
                    Typer::assertString($values['color'] ?? null),
                    Typer::parseNullableString($values['size'] ?? null) ?? NoticeboardCardSizeEnum::Medium->value,
                    Typer::parseNullableString($values['expires_on'] ?? null),
                    null,
                )->getKey();
            } catch (InvalidArgumentException) {
                Thrower::default()->message('body_html', \__('The card content must contain visible text.'))->throw();
            }
        }

        $card = $this->noticeboardCard(
            $actor,
            $store,
            Typer::assertInt($targetId),
            \in_array($identifier, ['restore_noticeboard_card', 'delete_noticeboard_card_permanently'], true),
            \in_array($identifier, ['restore_noticeboard_card', 'delete_noticeboard_card_permanently'], true),
        );

        if ($identifier === 'update_noticeboard_card') {
            try {
                $this->noticeboard->update(
                    $card,
                    $actor,
                    Typer::assertString($values['body_html'] ?? null),
                    Typer::assertString($values['label'] ?? null),
                    Typer::assertString($values['color'] ?? null),
                    Typer::parseNullableString($values['size'] ?? null) ?? $card->getSize()->value,
                    Typer::parseNullableString($values['expires_on'] ?? null),
                    null,
                    Typer::parseBool($values['remove_image'] ?? false),
                    Typer::parseInt($context['lock_version'] ?? null),
                );
            } catch (InvalidArgumentException) {
                Thrower::default()->message('body_html', \__('The card content must contain visible text.'))->throw();
            }
        } elseif ($identifier === 'trash_noticeboard_card') {
            $this->noticeboard->trash($card, $actor);
        } elseif ($identifier === 'restore_noticeboard_card') {
            $this->noticeboard->restore($card, $actor);
        } elseif ($identifier === 'delete_noticeboard_card_permanently') {
            if (!$this->noticeboard->forceDelete($card, $actor)) {
                Thrower::default()->message('card', \__('The card image could not be deleted.'))->throw();
            }
        } else {
            throw new InvalidArgumentException('Unknown daily-operations action.');
        }

        return $card->getKey();
    }

    /**
     * Validate business values with the same validity classes as human forms.
     *
     * @param array<string, mixed> $context
     * @param array<string, mixed> $values
     */
    private function validate(string $identifier, User $actor, array $context, array $values): void
    {
        $checklist = ChecklistValidity::inject($actor->resolveScopeUser()->getKey());
        $card = NoticeboardCardValidity::inject();
        $payload = [...$context, ...$values];
        $rules = match ($identifier) {
            'update_checklist_item' => ['completed' => $checklist->completed()->required()->toArray(), 'worker_id' => $checklist->workerId()->nullable()->toArray(), 'lock_version' => $checklist->lockVersion()->required()->toArray()],
            'excuse_checklist_day', 'restore_checklist_day' => ['reason' => $checklist->reason()->required()->toArray()],
            'replace_checklist_template' => ['scope' => $checklist->scope()->required()->toArray(), 'weekday' => $checklist->weekday()->nullable()->toArray(), 'shift' => $checklist->shift()->required()->toArray(), 'tasks' => $checklist->tasks()->required()->toArray(), 'tasks.*.text' => $checklist->taskText()->required()->toArray()],
            'create_noticeboard_card' => ['body_html' => $card->bodyHtml()->required()->toArray(), 'label' => $card->label()->required()->toArray(), 'color' => $card->color()->required()->toArray(), 'size' => $card->size()->nullable()->toArray(), 'expires_on' => $card->expiresOn()->nullable()->toArray()],
            'update_noticeboard_card' => ['body_html' => $card->bodyHtml()->required()->toArray(), 'label' => $card->label()->required()->toArray(), 'color' => $card->color()->required()->toArray(), 'size' => $card->size()->nullable()->toArray(), 'expires_on' => $card->expiresOn()->nullable()->toArray(), 'remove_image' => $card->removeImage()->nullable()->toArray(), 'lock_version' => $card->lockVersion()->required()->toArray()],
            'trash_noticeboard_card', 'restore_noticeboard_card', 'delete_noticeboard_card_permanently' => [],
            default => throw new InvalidArgumentException('Unknown daily-operations action.'),
        };

        Resolver::resolveValidatorFactory()->make($payload, $rules)->validate();
    }

    /**
     * Resolve an operation target and enforce tenant/store scope.
     */
    private function resolveTarget(string $identifier, User $actor, Store $store, int|null $targetId): string|null
    {
        return match ($identifier) {
            'update_checklist_item' => $this->resolveChecklistItemTarget($actor, $store, Typer::assertInt($targetId)),
            'excuse_checklist_day', 'restore_checklist_day' => $this->resolveChecklistDayTarget($actor, $store, Typer::assertInt($targetId)),
            'update_noticeboard_card', 'trash_noticeboard_card' => $this->resolveNoticeboardTarget($actor, $store, Typer::assertInt($targetId)),
            'restore_noticeboard_card', 'delete_noticeboard_card_permanently' => $this->resolveNoticeboardTarget($actor, $store, Typer::assertInt($targetId), true),
            'replace_checklist_template', 'create_noticeboard_card' => null,
            default => throw new InvalidArgumentException('Unknown daily-operations action.'),
        };
    }

    /**
     * Resolve a checklist-item target and return its public type.
     */
    private function resolveChecklistItemTarget(User $actor, Store $store, int $targetId): string
    {
        $this->checklistItem($actor, $store, $targetId);

        return 'checklist_item';
    }

    /**
     * Resolve a checklist-day target and return its public type.
     */
    private function resolveChecklistDayTarget(User $actor, Store $store, int $targetId): string
    {
        $this->checklistDay($actor, $store, $targetId);

        return 'checklist_day';
    }

    /**
     * Resolve a noticeboard target and return its public type.
     */
    private function resolveNoticeboardTarget(User $actor, Store $store, int $targetId, bool $trashed = false): string
    {
        $this->noticeboardCard($actor, $store, $targetId, $trashed, $trashed);

        return 'noticeboard_card';
    }

    /**
     * Resolve an owned store.
     */
    private function store(User $actor, int $storeId): Store
    {
        $query = Store::query();
        Store::scopeForUser($query, $actor->resolveScopeUser());

        return Typer::assertInstance($query->whereKey($storeId)->firstOrFail(), Store::class);
    }

    /**
     * Resolve a checklist item within a store.
     */
    private function checklistItem(User $actor, Store $store, int $targetId): ChecklistItem
    {
        $item = ChecklistItem::query()->whereKey($targetId)->firstOrFail();

        if (!ChecklistDay::query()->whereKey($item->getChecklistDayId())->where('user_id', $actor->resolveScopeUser()->getKey())->where('store_id', $store->getKey())->exists()) {
            \abort(404);
        }

        return $item;
    }

    /**
     * Resolve a checklist day within a store.
     */
    private function checklistDay(User $actor, Store $store, int $targetId): ChecklistDay
    {
        return Typer::assertInstance(ChecklistDay::query()->where('user_id', $actor->resolveScopeUser()->getKey())->where('store_id', $store->getKey())->whereKey($targetId)->firstOrFail(), ChecklistDay::class);
    }

    /**
     * Resolve an owned worker.
     */
    private function worker(User $actor, int $workerId): Worker
    {
        $query = Worker::query();
        Worker::scopeForUser($query, $actor->resolveScopeUser());
        Worker::scopeActive($query);

        return Typer::assertInstance($query->whereKey($workerId)->firstOrFail(), Worker::class);
    }

    /**
     * Resolve a noticeboard card within tenant and store scope.
     */
    private function noticeboardCard(User $actor, Store $store, int $targetId, bool $withTrashed = false, bool $onlyTrashed = false): NoticeboardCard
    {
        $query = $withTrashed ? NoticeboardCard::query()->withTrashed() : NoticeboardCard::query();
        NoticeboardCard::scopeForUser($query, $actor->resolveScopeUser());
        NoticeboardCard::scopeForStore($query, $store->getKey());

        if ($onlyTrashed) {
            $query->onlyTrashed();
        }

        return Typer::assertInstance($query->whereKey($targetId)->firstOrFail(), NoticeboardCard::class);
    }

    /**
     * Decode one bounded JSON object.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private function json(array $arguments, string $key): array
    {
        return Typer::assertStringKeyArray(Typer::assertArray(\json_decode(
            Typer::parseNullableString($arguments[$key] ?? null) ?? '{}',
            true,
            32,
            \JSON_THROW_ON_ERROR,
        )));
    }

    /**
     * Describe exact normal effects shown before approval.
     *
     * @return list<string>
     */
    private function effects(string $identifier): array
    {
        return \str_contains($identifier, 'noticeboard')
            ? ['Runs the normal noticeboard lifecycle service.', 'Preserves sanitization, optimistic locking, image removal, and operational activity behavior.']
            : ['Runs the normal checklist lifecycle service.', 'Preserves checklist events, optimistic locking, and operational activity behavior.'];
    }
}
