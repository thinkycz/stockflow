<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Checklist;

use App\Enums\ChecklistShiftEnum;
use App\Enums\ChecklistTemplateScopeEnum;
use App\Models\ChecklistDay;
use App\Models\ChecklistItem;
use App\Models\ChecklistTemplateTask;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use App\Services\ChecklistService;
use App\Support\ActiveStoreResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Typer;

class ChecklistIndexController
{
    /**
     * History rows per page.
     */
    public const int TAKE = 30;

    /**
     * Render checklist template management and history.
     */
    public function __invoke(Request $request): Response
    {
        $admin = User::mustAuth();
        $store = ActiveStoreResolver::resolve($request, $admin);
        $payload = [
            'active_store' => $store instanceof Store ? ['id' => $store->getKey(), 'name' => $store->getName(), 'is_warehouse' => $store->isWarehouse()] : null,
            'templates' => $this->emptyTemplates(),
            'history' => ['data' => [], 'current_page' => 1, 'last_page' => 1, 'total' => 0],
            'history_detail' => null,
            'workers' => [],
            'filters' => [
                'tab' => $request->string('tab')->toString() === 'history' ? 'history' : 'templates',
                'scope' => $request->string('scope')->toString() === 'weekly' ? 'weekly' : 'daily',
                'weekday' => \max(1, \min(7, $request->integer('weekday', 1))),
                'from' => $request->string('from')->toString(),
                'to' => $request->string('to')->toString(),
                'status' => $request->string('status')->toString(),
                'worker_id' => $request->integer('worker_id') > 0 ? $request->integer('worker_id') : null,
            ],
        ];

        if (!$store instanceof Store || $store->isWarehouse()) {
            return Inertia::render('checklists/Index', $payload);
        }

        $service = new ChecklistService();
        $service->initializeStore($store);
        $payload['templates'] = $this->templates($store);
        $payload['workers'] = Worker::query()->where('user_id', $admin->getKey())->orderBy('first_name')->orderBy('last_name')->get()
            ->map(static fn(Worker $worker): array => ['id' => $worker->getKey(), 'name' => $worker->getFullName()])->all();
        $payload['history'] = $this->history($request, $store, $service);
        $payload['history_detail'] = $this->detail($request, $store, $service);

        return Inertia::render('checklists/Index', $payload);
    }

    /**
     * @return array{daily: array<string, list<array{id: int, text: string}>>, weekly: array<int, array<string, list<array{id: int, text: string}>>>}
     */
    private function emptyTemplates(): array
    {
        $shifts = ['morning' => [], 'afternoon' => []];

        return ['daily' => $shifts, 'weekly' => \array_fill(1, 7, $shifts)];
    }

    /**
     * @return array{daily: array<string, list<array{id: int, text: string}>>, weekly: array<int, array<string, list<array{id: int, text: string}>>>}
     */
    private function templates(Store $store): array
    {
        $result = $this->emptyTemplates();
        $tasks = ChecklistTemplateTask::query()->where('store_id', $store->getKey())->orderBy('position')->orderBy('id')->get();
        foreach ($tasks as $value) {
            $task = Typer::assertInstance($value, ChecklistTemplateTask::class);
            $row = ['id' => $task->getKey(), 'text' => $task->getText()];
            if ($task->getScope() === ChecklistTemplateScopeEnum::Daily) {
                $result['daily'][$task->getShift()->value][] = $row;
            } else {
                $result['weekly'][$task->getWeekday() ?? 1][$task->getShift()->value][] = $row;
            }
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function history(Request $request, Store $store, ChecklistService $service): array
    {
        $today = CarbonImmutable::now(ChecklistService::TIMEZONE);
        $query = ChecklistDay::query()->where('store_id', $store->getKey())->with('items');
        $fromInput = $request->string('from')->toString();
        $toInput = $request->string('to')->toString();
        $from = $fromInput !== '' ? $fromInput : $today->subDays(29)->toDateString();
        $to = $toInput !== '' ? $toInput : $today->toDateString();
        $query->whereBetween('date', [$from, $to]);
        if ($request->integer('worker_id') > 0) {
            $query->whereHas('items', static fn(Builder $itemQuery): Builder => $itemQuery->where('completed_by_worker_id', $request->integer('worker_id')));
        }
        $this->applyStatusFilter($query, $request->string('status')->toString(), $today->toDateString());
        $paginator = $query->orderByDesc('date')->paginate(self::TAKE)->withQueryString();
        $paginator->through(static function (ChecklistDay $day) use ($service): array {
            return [
                'id' => $day->getKey(),
                'date' => $day->getDate()->toDateString(),
                'morning_status' => $service->statusFor($day, ChecklistShiftEnum::Morning),
                'afternoon_status' => $service->statusFor($day, ChecklistShiftEnum::Afternoon),
                'excuse_reason' => $day->getExcuseReason(),
            ];
        });

        return Typer::assertStringKeyArray($paginator->toArray());
    }

    /**
     * Filter before pagination by a status present in either shift.
     *
     * @param Builder<ChecklistDay> $query
     */
    private function applyStatusFilter(Builder $query, string $status, string $today): void
    {
        if ($status === 'excused') {
            $query->whereNotNull('excused_at');

            return;
        }
        if (!\in_array($status, ['not_configured', 'completed', 'incomplete', 'in_progress'], true)) {
            return;
        }

        $query->whereNull('excused_at');
        $shifts = [ChecklistShiftEnum::Morning->value, ChecklistShiftEnum::Afternoon->value];
        $query->where(static function (Builder $statusQuery) use ($status, $today, $shifts): void {
            $conditions = [];
            foreach ($shifts as $shift) {
                $conditions[] = static function (Builder $shiftQuery) use ($status, $today, $shift): void {
                    if ($status === 'not_configured') {
                        $shiftQuery->whereDoesntHave('items', static fn(Builder $itemQuery): Builder => $itemQuery->where('shift', $shift));

                        return;
                    }

                    $shiftQuery->whereHas('items', static fn(Builder $itemQuery): Builder => $itemQuery->where('shift', $shift));
                    if ($status === 'completed') {
                        $shiftQuery->whereDoesntHave('items', static fn(Builder $itemQuery): Builder => $itemQuery->where('shift', $shift)->whereNull('completed_at'));

                        return;
                    }

                    $status === 'incomplete' ? $shiftQuery->whereDate('date', '<', $today) : $shiftQuery->whereDate('date', '>=', $today);
                    $shiftQuery->whereHas('items', static fn(Builder $itemQuery): Builder => $itemQuery->where('shift', $shift)->whereNull('completed_at'));
                };
            }

            $statusQuery->where($conditions[0])->orWhere($conditions[1]);
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function detail(Request $request, Store $store, ChecklistService $service): array|null
    {
        $id = $request->integer('day_id');
        if ($id < 1) { return null; }
        $day = ChecklistDay::query()->where('store_id', $store->getKey())->with(['items.completedByWorker'])->whereKey($id)->first();
        if (!$day instanceof ChecklistDay) { return null; }

        return [
            'id' => $day->getKey(), 'date' => $day->getDate()->toDateString(), 'excuse_reason' => $day->getExcuseReason(),
            'morning_status' => $service->statusFor($day, ChecklistShiftEnum::Morning),
            'afternoon_status' => $service->statusFor($day, ChecklistShiftEnum::Afternoon),
            'items' => $day->getItems()->map(static fn(ChecklistItem $item): array => [
                'id' => $item->getKey(), 'shift' => $item->getShift()->value, 'text' => $item->getText(),
                'completed_at' => $item->getCompletedAt()?->toJSON(), 'worker_name' => $item->getCompletedByWorker()?->getFullName(),
            ])->all(),
        ];
    }
}
