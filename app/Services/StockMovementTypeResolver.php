<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StockMovementTypeEnum;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;

class StockMovementTypeResolver
{
    /**
     * Resolve movement type from explicit adjustment mode or source/destination pair.
     */
    public function resolve(bool $isAdjustment, int|null $sourceStoreId, int|null $storeId): StockMovementTypeEnum
    {
        if ($isAdjustment) {
            return StockMovementTypeEnum::ADJUSTMENT;
        }

        if ($sourceStoreId === null && $storeId !== null) {
            return StockMovementTypeEnum::INCOMING;
        }

        if ($sourceStoreId !== null && $storeId !== null) {
            if ($sourceStoreId === $storeId) {
                $this->fail([
                    'store_id' => Typer::assertString(
                        \__('Source and destination stores must be different.'),
                    ),
                ]);
            }

            return StockMovementTypeEnum::OUTGOING;
        }

        $this->fail([
            'store_id' => Typer::assertString(\__('Destination store is required.')),
        ]);
    }

    /**
     * @param array<string, string> $messages
     */
    private function fail(array $messages): never
    {
        $validator = Resolver::resolveValidatorFactory()->make([], []);
        $thrower = new Thrower($validator);

        foreach ($messages as $key => $message) {
            $thrower->message($key, $message);
        }

        $thrower->throw();
    }
}
