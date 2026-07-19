<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Shift;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\ShiftValidity;
use App\Models\Shift;
use App\Models\ShiftPreset;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use App\Services\ShiftAssignmentService;
use App\Support\ActiveStoreResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Thinkycz\LaravelCore\Support\Typer;

class ShiftQuickAddController
{
    use ValidatesWebRequests;

    /**
     * Assign a selected preset to an employee and date.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $admin = User::mustAuth();
        $store = ActiveStoreResolver::resolve($request, $admin);

        if (!$store instanceof Store) {
            \abort(404);
        }

        $validity = ShiftValidity::inject($admin->getKey());
        $validated = $this->validateRequest($request, [
            'worker_id' => $validity->workerId()->required()->toArray(),
            'shift_preset_id' => $validity->presetId($store->getKey())->required()->toArray(),
            'date' => $validity->date()->required()->toArray(),
            'allow_overlap' => $validity->allowOverlap()->nullable()->toArray(),
        ]);
        $worker = Typer::assertInstance(Worker::query()->find($validated->parseInt('worker_id')), Worker::class);
        $preset = Typer::assertInstance(ShiftPreset::query()->find($validated->parseInt('shift_preset_id')), ShiftPreset::class);
        $date = $validated->assertString('date');
        $service = new ShiftAssignmentService();
        $existing = $service->findExact(
            $admin,
            $store,
            $worker,
            $date,
            $preset->getStartTimeShort(),
            $preset->getEndTimeShort(),
        );

        if ($existing instanceof Shift) {
            return new JsonResponse([
                'status' => 'exists',
                'shift' => self::shiftData($existing),
            ]);
        }

        $overlaps = $service->findOverlaps(
            $admin,
            $store,
            $worker,
            $date,
            $preset->getStartTimeShort(),
            $preset->getEndTimeShort(),
        );

        if ($overlaps->isNotEmpty() && !$validated->parseBool('allow_overlap')) {
            return new JsonResponse([
                'status' => 'overlap',
                'conflicts' => $overlaps->map(static fn(Shift $shift): array => [
                    'id' => $shift->getKey(),
                    'start_time' => $shift->getStartTimeShort(),
                    'end_time' => $shift->getEndTimeShort(),
                ])->all(),
            ], HttpResponse::HTTP_CONFLICT);
        }

        $shift = $service->create(
            $admin,
            $store,
            $worker,
            $date,
            $preset->getStartTimeShort(),
            $preset->getEndTimeShort(),
        );
        $minutes = $shift->getDurationMinutes();

        return new JsonResponse([
            'status' => 'created',
            'shift' => self::shiftData($shift),
            'contribution' => [
                'minutes' => $minutes,
                'salary' => \round(($minutes / 60) * $shift->getHourlyRate(), 2),
            ],
        ], HttpResponse::HTTP_CREATED);
    }

    /**
     * @return array{id: int, worker_id: int, date: string, start_time: string, end_time: string}
     */
    private static function shiftData(Shift $shift): array
    {
        return [
            'id' => $shift->getKey(),
            'worker_id' => $shift->getWorkerId(),
            'date' => $shift->getDate(),
            'start_time' => $shift->getStartTimeShort(),
            'end_time' => $shift->getEndTimeShort(),
        ];
    }
}
