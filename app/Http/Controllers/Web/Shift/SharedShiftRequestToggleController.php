<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Shift;

use App\Http\Controllers\Web\Concerns\ThrottlesWebRequests;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\ShiftRequestValidity;
use App\Models\ShiftRequest;
use App\Models\ShiftShareLink;
use App\Models\Store;
use App\Models\Worker;
use App\Services\ShiftRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Thinkycz\LaravelCore\Support\Typer;

class SharedShiftRequestToggleController
{
    use ThrottlesWebRequests;
    use ValidatesWebRequests;

    /**
     * Create, replace, or remove one public shift request.
     */
    public function __invoke(Request $request, string $token): JsonResponse
    {
        $store = ShiftShareLink::findStoreForToken($token);

        if (!$store instanceof Store) {
            \abort(404);
        }

        self::$throttle = 120;
        self::$decay = 1;
        $validity = ShiftRequestValidity::inject($store->getUserId());
        $validated = $this->validateRequest($request, [
            'worker_id' => $validity->workerId()->required()->toArray(),
            'date' => $validity->date()->required()->toArray(),
            'start_time' => $validity->startTime()->required()->toArray(),
            'end_time' => $validity->endTime()->required()->toArray(),
        ]);
        $this->hit($this->limit());
        $worker = Typer::assertInstance(Worker::query()->find($validated->parseInt('worker_id')), Worker::class);
        $result = (new ShiftRequestService())->toggle(
            $store,
            $worker,
            $validated->assertString('date'),
            $validated->assertString('start_time'),
            $validated->assertString('end_time'),
        );

        return new JsonResponse([
            'status' => $result['status'],
            'request' => $result['request'] instanceof ShiftRequest ? self::shiftRequestData($result['request']) : null,
        ], $result['status'] === 'created' ? HttpResponse::HTTP_CREATED : HttpResponse::HTTP_OK);
    }

    /**
     * @return array{id: int, worker_id: int, date: string, start_time: string, end_time: string}
     */
    private static function shiftRequestData(ShiftRequest $shiftRequest): array
    {
        return [
            'id' => $shiftRequest->getKey(),
            'worker_id' => $shiftRequest->getWorkerId(),
            'date' => $shiftRequest->getDate(),
            'start_time' => $shiftRequest->getStartTimeShort(),
            'end_time' => $shiftRequest->getEndTimeShort(),
        ];
    }
}
