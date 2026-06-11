<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Typer;

class DatabaseToken extends BaseModel
{
    /**
     * Plain text bearer.
     */
    public string|null $bearer = null;

    /**
     * @inheritDoc
     */
    protected $hidden = ['hash', 'bearer'];

    /**
     * Inject.
     */
    public static function inject(): self
    {
        return new self();
    }

    /**
     * Find database token matching given bearer.
     */
    public function findByBearer(string $bearer): static|null
    {
        if (!\str_contains($bearer, '|')) {
            return null;
        }

        [$id, $token] = \explode('|', $bearer, 2);

        $key = \filter_var($id, \FILTER_VALIDATE_INT);

        if ($key === false) {
            return null;
        }

        $instance = static::findByKey($key);

        if ($instance === null) {
            return null;
        }

        if (!\hash_equals($instance->mustString('hash'), \hash('sha256', $token))) {
            return null;
        }

        return $instance;
    }

    /**
     * Login user.
     */
    public function login(string $guardName, BaseUser $user): static
    {
        $token = Str::random(40);
        $hash = \hash('sha256', $token);

        $this->setAttribute('hash', $hash);

        $this->relationship($guardName, $user)->associate($user);

        $this->save();

        $this->bearer = "{$this->getKey()}|{$token}";

        return $this;
    }

    /**
     * Get user.
     */
    public function user(string $guardName): BaseUser|null
    {
        return Typer::assertNullableInstance($this->relationship($guardName, null)->getResults(), BaseUser::class);
    }

    /**
     * Relationship.
     *
     * @return BelongsTo<BaseUser, $this>
     */
    protected function relationship(string $guardName, BaseUser|null $user): BelongsTo
    {
        $instance = new (Config::inject()->assertA("auth.providers.{$guardName}.model", BaseUser::class))();

        return $this->belongsTo($instance::class, $instance->getForeignKey(), $instance->getKeyName(), 'relationship');
    }
}
