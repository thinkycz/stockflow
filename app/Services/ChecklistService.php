<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ChecklistEventActionEnum;
use App\Enums\ChecklistShiftEnum;
use App\Enums\ChecklistTemplateScopeEnum;
use App\Enums\OperationalActivityTypeEnum;
use App\Enums\StoreStatusEnum;
use App\Models\ChecklistDay;
use App\Models\ChecklistEvent;
use App\Models\ChecklistItem;
use App\Models\ChecklistTemplateTask;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use App\Support\ChecklistDefaultTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class ChecklistService
{
    /**
     * Business timezone used for checklist dates.
     */
    public const string TIMEZONE = 'Europe/Prague';

    /**
     * Seed the default template for a retail store exactly once.
     */
    public function initializeStore(Store $store): void
    {
        if ($store->isWarehouse()) {
            return;
        }

        DB::transaction(static function () use ($store): void {
            $lockedStore = Typer::assertInstance(Store::query()->whereKey($store->getKey())->lockForUpdate()->firstOrFail(), Store::class);

            if ($lockedStore->getChecklistsInitializedAt() !== null) {
                return;
            }

            $now = CarbonImmutable::now();
            ChecklistTemplateTask::query()->insert(\array_map(
                static fn(array $task): array => [
                    'user_id' => $store->getUserId(),
                    'store_id' => $store->getKey(),
                    ...$task,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ChecklistDefaultTemplate::tasks(),
            ));

            $lockedStore->setAttribute('checklists_initialized_at', $now);
            $lockedStore->save();
        });
    }

    /**
     * Ensure and return one immutable daily snapshot.
     */
    public function ensureDay(Store $store, CarbonImmutable $date): ChecklistDay
    {
        if ($store->isWarehouse() || $store->getStatus() !== StoreStatusEnum::ACTIVE) {
            throw new InvalidArgumentException('Checklists are available only for active retail stores.');
        }

        $this->initializeStore($store);

        return DB::transaction(function () use ($store, $date): ChecklistDay {
            $existing = ChecklistDay::query()
                ->where('store_id', $store->getKey())
                ->whereDate('date', $date->toDateString())
                ->lockForUpdate()
                ->first();

            if ($existing instanceof ChecklistDay) {
                return $existing;
            }

            $day = ChecklistDay::query()->create([
                'user_id' => $store->getUserId(),
                'store_id' => $store->getKey(),
                'date' => $date->toDateString(),
                'excused_by_user_id' => null,
                'excuse_reason' => null,
                'excused_at' => null,
            ]);

            $tasks = ChecklistTemplateTask::query()
                ->where('store_id', $store->getKey())
                ->where(static function (Builder $query) use ($date): void {
                    $query->where('scope', ChecklistTemplateScopeEnum::Daily->value)
                        ->orWhere(static function (Builder $query) use ($date): void {
                            $query->where('scope', ChecklistTemplateScopeEnum::Weekly->value)
                                ->where('weekday', $date->isoWeekday());
                        });
                })
                ->orderByRaw('case when scope = \'daily\' then 0 else 1 end')
                ->orderBy('position')
                ->orderBy('id')
                ->get();

            $positions = [ChecklistShiftEnum::Morning->value => 0, ChecklistShiftEnum::Afternoon->value => 0];
            $items = [];
            $now = CarbonImmutable::now();
            foreach ($tasks as $task) {
                $template = Typer::assertInstance($task, ChecklistTemplateTask::class);
                $shift = $template->getShift()->value;
                $items[] = [
                    'checklist_day_id' => $day->getKey(),
                    'template_task_id' => $template->getKey(),
                    'shift' => $shift,
                    'text' => $template->getText(),
                    'position' => ++$positions[$shift],
                    'completed_by_worker_id' => null,
                    'completed_by_user_id' => null,
                    'completed_at' => null,
                    'lock_version' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if ($items !== []) {
                ChecklistItem::query()->insert($items);
            }

            return $day;
        });
    }

    /**
     * @param list<string> $texts
     */
    public function replaceTemplateGroup(
        Store $store,
        ChecklistTemplateScopeEnum $scope,
        int|null $weekday,
        ChecklistShiftEnum $shift,
        array $texts,
    ): void {
        if ($store->isWarehouse()) {
            throw new InvalidArgumentException('Warehouse checklists are not supported.');
        }
        if (($scope === ChecklistTemplateScopeEnum::Daily && $weekday !== null) ||
            ($scope === ChecklistTemplateScopeEnum::Weekly && ($weekday === null || $weekday < 1 || $weekday > 7))) {
            throw new InvalidArgumentException('Invalid checklist template scope.');
        }

        DB::transaction(static function () use ($store, $scope, $weekday, $shift, $texts): void {
            $query = ChecklistTemplateTask::query()
                ->where('store_id', $store->getKey())
                ->where('scope', $scope->value)
                ->where('shift', $shift->value);
            $weekday === null ? $query->whereNull('weekday') : $query->where('weekday', $weekday);
            $query->delete();

            foreach ($texts as $position => $text) {
                ChecklistTemplateTask::query()->create([
                    'user_id' => $store->getUserId(),
                    'store_id' => $store->getKey(),
                    'scope' => $scope->value,
                    'weekday' => $weekday,
                    'shift' => $shift->value,
                    'text' => $text,
                    'position' => $position + 1,
                ]);
            }
        });
    }

    /**
     * Complete or reopen one current-day item with optimistic locking.
     */
    public function updateItem(
        ChecklistItem $item,
        Store $store,
        User $actor,
        Worker|null $worker,
        bool $completed,
        int $version,
    ): ChecklistItem {
        return DB::transaction(function () use ($item, $store, $actor, $worker, $completed, $version): ChecklistItem {
            $locked = Typer::assertInstance(ChecklistItem::query()->whereKey($item->getKey())->lockForUpdate()->firstOrFail(), ChecklistItem::class);
            $day = Typer::assertInstance(ChecklistDay::query()->whereKey($locked->getChecklistDayId())->lockForUpdate()->firstOrFail(), ChecklistDay::class);

            if ($day->getStoreId() !== $store->getKey() || $day->getDate()->toDateString() !== CarbonImmutable::now(self::TIMEZONE)->toDateString()) {
                throw new InvalidArgumentException('Only today\'s checklist can be changed.');
            }
            if ($day->isExcused()) {
                throw new InvalidArgumentException('An excused checklist cannot be changed.');
            }
            if ($version !== $locked->getLockVersion()) {
                throw new RuntimeException('Checklist item was changed by another user.');
            }
            if ($completed && !$worker instanceof Worker) {
                throw new InvalidArgumentException('A worker is required.');
            }
            if ($worker instanceof Worker && $worker->getUserId() !== $actor->resolveScopeUser()->getKey()) {
                throw new InvalidArgumentException('Worker does not belong to this company.');
            }

            $shift = $locked->getShift();
            $beforeStatus = $this->statusFor($day, $shift);
            $previousWorkerId = $locked->getCompletedByWorkerId();
            $completedWorkerId = $completed ? $worker->getKey() : null;
            $locked->setAttribute('completed_by_worker_id', $completedWorkerId);
            $locked->setAttribute('completed_by_user_id', $completed ? $actor->getKey() : null);
            $locked->setAttribute('completed_at', $completed ? CarbonImmutable::now() : null);
            $locked->setAttribute('lock_version', $version + 1);
            $locked->save();

            ChecklistEvent::query()->create([
                'checklist_day_id' => $day->getKey(),
                'checklist_item_id' => $locked->getKey(),
                'actor_user_id' => $actor->getKey(),
                'worker_id' => $completed ? $completedWorkerId : $previousWorkerId,
                'action' => $completed ? ChecklistEventActionEnum::Completed->value : ChecklistEventActionEnum::Reopened->value,
                'created_at' => CarbonImmutable::now(),
            ]);

            $afterStatus = $this->statusFor($day, $shift);
            if ($beforeStatus !== 'completed' && $afterStatus === 'completed') {
                $this->notifyChecklist(
                    OperationalActivityTypeEnum::CHECKLIST_SHIFT_COMPLETED,
                    $actor,
                    $store,
                    $day,
                    $shift,
                );
            } elseif ($beforeStatus === 'completed' && $afterStatus !== 'completed') {
                $this->notifyChecklist(
                    OperationalActivityTypeEnum::CHECKLIST_SHIFT_REOPENED,
                    $actor,
                    $store,
                    $day,
                    $shift,
                );
            }

            return $locked;
        });
    }

    /**
     * Apply or revoke an audited administrative day excuse.
     */
    public function excuseDay(ChecklistDay $day, User $actor, string $reason, bool $excused): void
    {
        DB::transaction(function () use ($day, $actor, $reason, $excused): void {
            $locked = Typer::assertInstance(ChecklistDay::query()->whereKey($day->getKey())->lockForUpdate()->firstOrFail(), ChecklistDay::class);
            $locked->setAttribute('excused_by_user_id', $excused ? $actor->getKey() : null);
            $locked->setAttribute('excuse_reason', $excused ? $reason : null);
            $locked->setAttribute('excused_at', $excused ? CarbonImmutable::now() : null);
            $locked->save();

            ChecklistEvent::query()->create([
                'checklist_day_id' => $locked->getKey(),
                'actor_user_id' => $actor->getKey(),
                'action' => $excused ? ChecklistEventActionEnum::Excused->value : ChecklistEventActionEnum::ExcuseRevoked->value,
                'reason' => $reason,
                'created_at' => CarbonImmutable::now(),
            ]);

            $store = Typer::assertInstance(Store::query()->whereKey($locked->getStoreId())->firstOrFail(), Store::class);
            $this->notifyChecklist(
                $excused
                    ? OperationalActivityTypeEnum::CHECKLIST_DAY_EXCUSED
                    : OperationalActivityTypeEnum::CHECKLIST_DAY_EXCUSE_REVOKED,
                $actor,
                $store,
                $locked,
            );
        });
    }

    /**
     * Derive the display status of one shift.
     */
    public function statusFor(ChecklistDay $day, ChecklistShiftEnum $shift): string
    {
        if ($day->isExcused()) {
            return 'excused';
        }
        $items = $day->getItems()->filter(static fn(ChecklistItem $item): bool => $shift === $item->getShift());
        $total = $items->count();
        if ($total === 0) {
            return 'not_configured';
        }
        $completed = $items->filter(static fn(ChecklistItem $item): bool => $item->isCompleted())->count();
        if ($completed === $total) {
            return 'completed';
        }

        return $day->getDate()->toDateString() < CarbonImmutable::now(self::TIMEZONE)->toDateString() ? 'incomplete' : 'in_progress';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function dashboardPayload(Store $store, User $actor): array|null
    {
        if ($store->isWarehouse() || $store->getStatus() !== StoreStatusEnum::ACTIVE) {
            return null;
        }

        $day = $this->ensureDay($store, CarbonImmutable::now(self::TIMEZONE));
        $items = $day->items()->with('completedByWorker')->orderBy('shift')->orderBy('position')->get();
        $day->setRelation('items', $items);
        $byShift = [ChecklistShiftEnum::Morning->value => [], ChecklistShiftEnum::Afternoon->value => []];
        foreach ($items as $value) {
            $item = Typer::assertInstance($value, ChecklistItem::class);
            $byShift[$item->getShift()->value][] = [
                'id' => $item->getKey(),
                'text' => $item->getText(),
                'completed' => $item->isCompleted(),
                'completed_at' => $item->getCompletedAt()?->toJSON(),
                'worker_name' => $item->getCompletedByWorker()?->getFullName(),
                'version' => $item->getLockVersion(),
            ];
        }

        $workers = Worker::query()->where('user_id', $actor->resolveScopeUser()->getKey())->orderBy('first_name')->orderBy('last_name')->get()
            ->map(static fn(Worker $worker): array => ['id' => $worker->getKey(), 'name' => $worker->getFullName()])->all();

        return [
            'day_id' => $day->getKey(),
            'date' => $day->getDate()->toDateString(),
            'editable' => !$day->isExcused(),
            'excuse_reason' => $day->getExcuseReason(),
            'workers' => $workers,
            'shifts' => [
                'morning' => ['status' => $this->statusFor($day, ChecklistShiftEnum::Morning), 'items' => $byShift['morning']],
                'afternoon' => ['status' => $this->statusFor($day, ChecklistShiftEnum::Afternoon), 'items' => $byShift['afternoon']],
            ],
        ];
    }

    /**
     * Dispatch one aggregate checklist milestone.
     */
    private function notifyChecklist(
        OperationalActivityTypeEnum $type,
        User $actor,
        Store $store,
        ChecklistDay $day,
        ChecklistShiftEnum|null $shift = null,
    ): void {
        $facts = ['Slack checklist date' => $day->getDate()->format('j. n. Y')];
        if ($shift instanceof ChecklistShiftEnum) {
            $facts['Slack checklist shift'] = match ($shift) {
                ChecklistShiftEnum::Morning => 'Ranní',
                ChecklistShiftEnum::Afternoon => 'Odpolední',
            };
        }

        OperationalActivityService::dispatch(
            $type,
            $actor,
            CarbonImmutable::now('UTC')->toIso8601String(),
            Resolver::resolveUrlGenerator()->route('checklists.index', [
                'store_id' => $store->getKey(),
                'date' => $day->getDate()->toDateString(),
            ]),
            [['store' => $store, 'perspective' => null]],
            $facts,
        );
    }
}
