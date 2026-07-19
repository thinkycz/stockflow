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
    public function resolve(string $mode, int|null $sourceStoreId, int|null $storeId): StockMovementTypeEnum
    {
        if ($mode === 'adjustment') {
            return StockMovementTypeEnum::ADJUSTMENT;
        }

        if ($mode === 'consumption') {
            if ($storeId !== null && $sourceStoreId === null) {
                return StockMovementTypeEnum::CONSUMPTION;
            }

            $this->fail([
                'store_id' => Typer::assertString(\__('Consumption requires one store and no source store.')),
            ]);
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

            return StockMovementTypeEnum::TRANSFER;
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
