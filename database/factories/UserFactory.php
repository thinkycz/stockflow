<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    public static string|null $password = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => Carbon::now(),
            'password' => static::$password ??= 'password',
            'remember_token' => Str::random(10),
            'locale' => Config::inject()->assertString('app.locale'),
            'is_admin' => false,
            'parent_user_id' => null,
            'assigned_store_id' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state([
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model is the main admin.
     */
    public function admin(): static
    {
        return $this->state([
            'is_admin' => true,
            'parent_user_id' => null,
            'assigned_store_id' => null,
        ]);
    }

    /**
     * Indicate that the model is a limited user assigned to the given store.
     */
    public function limited(Store $store): static
    {
        return $this->state([
            'is_admin' => false,
            'parent_user_id' => $store->getUserId(),
            'assigned_store_id' => $store->getKey(),
        ]);
    }

    /**
     * Create model with password filled.
     */
    public function password(string|null $password = null): static
    {
        return $this->set('password', Resolver::resolveHasher()->make($password ?? static::$password ?? 'password'));
    }
}
