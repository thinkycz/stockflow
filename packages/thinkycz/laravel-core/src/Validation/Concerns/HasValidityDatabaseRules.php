<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Validation\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Thinkycz\LaravelCore\Support\Typer;
use Thinkycz\LaravelCore\Validation\Rules\CallbackRule;

trait HasValidityDatabaseRules
{
    /**
     * Add exists rule.
     *
     * @param array<int, string> $wheres
     *
     * @return $this
     */
    public function exists(string $table, string $column, array $wheres = []): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('exists', [$table, $column, ...$wheres]);
    }

    /**
     * Add unique rule.
     *
     * @param array<int, string> $wheres
     *
     * @return $this
     */
    public function unique(string $table, string $column, mixed $id = null, string|null $idColumn = null, array $wheres = []): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('unique', [$table, $column, $id, $idColumn, ...$wheres]);
    }

    /**
     * Add pluck rule.
     *
     * @template T of Builder
     *
     * @param Closure(): T $callback
     * @param (Closure(mixed, mixed=, mixed=): (bool|int|null))|null $each
     *
     * @return $this
     */
    public function pluck(Closure $callback, string $column = 'id', Closure|null $each = null, int|string $message = 'validation.invalid'): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        $keys = null;

        return $this->addRule(
            new CallbackRule(static function (mixed $value, mixed $attribute = null) use ($callback, $each, $column, &$keys): bool|int {
                if ($keys === null) {
                    $builder = $callback();
                    $keys = $builder->getQuery()->distinct()->pluck($builder->qualifyColumn($column));
                }

                $exists = $keys->contains($value);

                if (!$exists) {
                    return false;
                }

                if ($each === null) {
                    return true;
                }

                foreach ($keys as $found) {
                    $ok = $each($found, $value, $attribute);

                    if ($ok === null) {
                        continue;
                    }

                    return $ok;
                }

                return true;
            }, $message),
        );
    }

    /**
     * Add not pluck rule.
     *
     * @template T of Builder
     *
     * @param Closure(): T $callback
     * @param (Closure(mixed, mixed=, mixed=): (bool|int|null))|null $each
     *
     * @return $this
     */
    public function notPluck(Closure $callback, string $column = 'id', Closure|null $each = null, int|string $message = 'validation.invalid'): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        $keys = null;

        return $this->addRule(
            new CallbackRule(static function (mixed $value, mixed $attribute = null) use ($callback, $each, $column, &$keys): bool|int {
                if ($keys === null) {
                    $builder = $callback();
                    $keys = $builder->getQuery()->distinct()->pluck($builder->qualifyColumn($column));
                }

                $exists = $keys->contains($value);

                if (!$exists) {
                    return true;
                }

                if ($each === null) {
                    return false;
                }

                foreach ($keys as $found) {
                    $ok = $each($found, $value, $attribute);

                    if ($ok === null) {
                        continue;
                    }

                    return $ok;
                }

                return false;
            }, $message),
        );
    }

    /**
     * Add pluck key rule.
     *
     * @template T of Builder
     *
     * @param Closure(): T $callback
     * @param (Closure(mixed, mixed=, mixed=): (bool|int|null))|null $each
     *
     * @return $this
     */
    public function pluckKey(Closure $callback, Closure|null $each = null, int|string $message = 'validation.invalid'): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        $keys = null;

        return $this->addRule(
            new CallbackRule(static function (mixed $value, mixed $attribute = null) use ($callback, $each, &$keys): bool|int {
                if ($keys === null) {
                    $builder = $callback();

                    $model = Typer::assertInstance($builder->getModel(), Model::class);

                    $keys = $builder->getQuery()->distinct()->pluck($builder->qualifyColumn($model->getKeyName()));
                }

                $exists = $keys->contains($value);

                if (!$exists) {
                    return false;
                }

                if ($each === null) {
                    return true;
                }

                foreach ($keys as $found) {
                    $ok = $each($found, $value, $attribute);

                    if ($ok === null) {
                        continue;
                    }

                    return $ok;
                }

                return true;
            }, $message),
        );
    }

    /**
     * Add not pluck key rule.
     *
     * @template T of Builder
     *
     * @param Closure(): T $callback
     * @param (Closure(mixed, mixed=, mixed=): (bool|int|null))|null $each
     *
     * @return $this
     */
    public function notPluckKey(Closure $callback, Closure|null $each = null, int|string $message = 'validation.invalid'): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        $keys = null;

        return $this->addRule(
            new CallbackRule(static function (mixed $value, mixed $attribute = null) use ($callback, $each, &$keys): bool|int {
                if ($keys === null) {
                    $builder = $callback();

                    $model = Typer::assertInstance($builder->getModel(), Model::class);

                    $keys = $builder->getQuery()->distinct()->pluck($builder->qualifyColumn($model->getKeyName()));
                }

                $exists = $keys->contains($value);

                if (!$exists) {
                    return true;
                }

                if ($each === null) {
                    return false;
                }

                foreach ($keys as $found) {
                    $ok = $each($found, $value, $attribute);

                    if ($ok === null) {
                        continue;
                    }

                    return $ok;
                }

                return false;
            }, $message),
        );
    }

    /**
     * Add pluck route key rule.
     *
     * @template T of Builder
     *
     * @param Closure(): T $callback
     * @param (Closure(mixed, mixed=, mixed=): (bool|int|null))|null $each
     *
     * @return $this
     */
    public function pluckRouteKey(Closure $callback, Closure|null $each = null, int|string $message = 'validation.invalid'): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        $keys = null;

        return $this->addRule(
            new CallbackRule(static function (mixed $value, mixed $attribute = null) use ($callback, $each, &$keys): bool|int {
                if ($keys === null) {
                    $builder = $callback();

                    $model = Typer::assertInstance($builder->getModel(), Model::class);

                    $keys = $builder->getQuery()->distinct()->pluck($builder->qualifyColumn($model->getRouteKeyName()));
                }

                $exists = $keys->contains($value);

                if (!$exists) {
                    return false;
                }

                if ($each === null) {
                    return true;
                }

                foreach ($keys as $found) {
                    $ok = $each($found, $value, $attribute);

                    if ($ok === null) {
                        continue;
                    }

                    return $ok;
                }

                return true;
            }, $message),
        );
    }

    /**
     * Add not pluck route key rule.
     *
     * @template T of Builder
     *
     * @param Closure(): T $callback
     * @param (Closure(mixed, mixed=, mixed=): (bool|int|null))|null $each
     *
     * @return $this
     */
    public function notPluckRouteKey(Closure $callback, Closure|null $each = null, int|string $message = 'validation.invalid'): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        $keys = null;

        return $this->addRule(
            new CallbackRule(static function (mixed $value, mixed $attribute = null) use ($callback, $each, &$keys): bool|int {
                if ($keys === null) {
                    $builder = $callback();

                    $model = Typer::assertInstance($builder->getModel(), Model::class);

                    $keys = $builder->getQuery()->distinct()->pluck($builder->qualifyColumn($model->getRouteKeyName()));
                }

                $exists = $keys->contains($value);

                if (!$exists) {
                    return true;
                }

                if ($each === null) {
                    return false;
                }

                foreach ($keys as $found) {
                    $ok = $each($found, $value, $attribute);

                    if ($ok === null) {
                        continue;
                    }

                    return $ok;
                }

                return false;
            }, $message),
        );
    }

    /**
     * Add builder rule.
     *
     * @template T of Builder
     *
     * @param Closure(mixed=, mixed=): T $callback
     * @param (Closure(Model, mixed=, mixed=): (bool|int|null))|null $each
     *
     * @return $this
     */
    public function builder(Closure $callback, Closure|null $each = null, int|string $message = 'validation.invalid'): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule(
            new CallbackRule(static function (mixed $value, mixed $attribute = null) use ($callback, $each): bool|int {
                $builder = $callback($value, $attribute);

                $exists = $builder->toBase()->exists();

                if (!$exists) {
                    return false;
                }

                if ($each === null) {
                    return true;
                }

                foreach ($builder->cursor() as $found) {
                    $ok = $each(Typer::assertInstance($found, Model::class), $value, $attribute);

                    if ($ok === null) {
                        continue;
                    }

                    return $ok;
                }

                return true;
            }, $message),
        );
    }

    /**
     * Add not builder rule.
     *
     * @template T of Builder
     *
     * @param Closure(mixed=, mixed=): T $callback
     * @param (Closure(Model, mixed=, mixed=): (bool|int|null))|null $each
     *
     * @return $this
     */
    public function notBuilder(Closure $callback, Closure|null $each = null, int|string $message = 'validation.invalid'): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule(
            new CallbackRule(static function (mixed $value, mixed $attribute = null) use ($callback, $each): bool|int {
                $builder = $callback($value, $attribute);

                $exists = $builder->toBase()->exists();

                if (!$exists) {
                    return true;
                }

                if ($each === null) {
                    return false;
                }

                foreach ($builder->cursor() as $found) {
                    $ok = $each(Typer::assertInstance($found, Model::class), $value, $attribute);

                    if ($ok === null) {
                        continue;
                    }

                    return $ok;
                }

                return false;
            }, $message),
        );
    }

    /**
     * Add builder key rule.
     *
     * @template T of Builder
     *
     * @param Closure(mixed=, mixed=): T $callback
     * @param (Closure(Model, mixed=, mixed=): (bool|int|null))|null $each
     *
     * @return $this
     */
    public function builderKey(Closure $callback, Closure|null $each = null, int|string $message = 'validation.invalid'): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule(
            new CallbackRule(static function (mixed $value, mixed $attribute = null) use ($callback, $each): bool|int {
                $builder = $callback($value, $attribute);

                $builder->whereKey($value);

                $exists = $builder->toBase()->exists();

                if (!$exists) {
                    return false;
                }

                if ($each === null) {
                    return true;
                }

                foreach ($builder->cursor() as $found) {
                    $ok = $each(Typer::assertInstance($found, Model::class), $value, $attribute);

                    if ($ok === null) {
                        continue;
                    }

                    return $ok;
                }

                return true;
            }, $message),
        );
    }

    /**
     * Add not builder key rule.
     *
     * @template T of Builder
     *
     * @param Closure(mixed=, mixed=): T $callback
     * @param (Closure(Model, mixed=, mixed=): (bool|int|null))|null $each
     *
     * @return $this
     */
    public function notBuilderKey(Closure $callback, Closure|null $each = null, int|string $message = 'validation.invalid'): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule(
            new CallbackRule(static function (mixed $value, mixed $attribute = null) use ($callback, $each): bool|int {
                $builder = $callback($value, $attribute);

                $builder->whereKey($value);

                $exists = $builder->toBase()->exists();

                if (!$exists) {
                    return true;
                }

                if ($each === null) {
                    return false;
                }

                foreach ($builder->cursor() as $found) {
                    $ok = $each(Typer::assertInstance($found, Model::class), $value, $attribute);

                    if ($ok === null) {
                        continue;
                    }

                    return $ok;
                }

                return false;
            }, $message),
        );
    }

    /**
     * Add builder route key rule.
     *
     * @template T of Builder
     *
     * @param Closure(mixed=, mixed=): T $callback
     * @param (Closure(Model, mixed=, mixed=): (bool|int|null))|null $each
     *
     * @return $this
     */
    public function builderRouteKey(Closure $callback, Closure|null $each = null, int|string $message = 'validation.invalid'): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule(
            new CallbackRule(static function (mixed $value, mixed $attribute = null) use ($callback, $each): bool|int {
                $builder = $callback($value, $attribute);

                $model = Typer::assertInstance($builder->getModel(), Model::class);

                $builder->where($model->getRouteKeyName(), $value);

                $exists = $builder->toBase()->exists();

                if (!$exists) {
                    return false;
                }

                if ($each === null) {
                    return true;
                }

                foreach ($builder->cursor() as $found) {
                    $ok = $each(Typer::assertInstance($found, Model::class), $value, $attribute);

                    if ($ok === null) {
                        continue;
                    }

                    return $ok;
                }

                return true;
            }, $message),
        );
    }

    /**
     * Add not builder route key rule.
     *
     * @template T of Builder
     *
     * @param Closure(mixed=, mixed=): T $callback
     * @param (Closure(Model, mixed=, mixed=): (bool|int|null))|null $each
     *
     * @return $this
     */
    public function notBuilderRouteKey(Closure $callback, Closure|null $each = null, int|string $message = 'validation.invalid'): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule(
            new CallbackRule(static function (mixed $value, mixed $attribute = null) use ($callback, $each): bool|int {
                $builder = $callback($value, $attribute);

                $model = Typer::assertInstance($builder->getModel(), Model::class);

                $builder->where($model->getRouteKeyName(), $value);

                $exists = $builder->toBase()->exists();

                if (!$exists) {
                    return true;
                }

                if ($each === null) {
                    return false;
                }

                foreach ($builder->cursor() as $found) {
                    $ok = $each(Typer::assertInstance($found, Model::class), $value, $attribute);

                    if ($ok === null) {
                        continue;
                    }

                    return $ok;
                }

                return false;
            }, $message),
        );
    }

    /**
     * Add builder id rule.
     *
     * @template T of Builder
     *
     * @param Closure(mixed=, mixed=): T $callback
     * @param (Closure(Model, mixed=, mixed=): bool)|null $each
     *
     * @return $this
     */
    public function builderId(Closure $callback, Closure|null $each = null, string $message = 'validation.invalid'): static
    {
        return $this->builderKey($callback, $each, $message);
    }

    /**
     * Add not builder id rule.
     *
     * @template T of Builder
     *
     * @param Closure(mixed=, mixed=): T $callback
     * @param (Closure(Model, mixed=, mixed=): bool)|null $each
     *
     * @return $this
     */
    public function notBuilderId(Closure $callback, Closure|null $each = null, string $message = 'validation.invalid'): static
    {
        return $this->notBuilderKey($callback, $each, $message);
    }

    /**
     * Add builder slug rule.
     *
     * @template T of Builder
     *
     * @param Closure(mixed=, mixed=): T $callback
     * @param (Closure(Model, mixed=, mixed=): bool)|null $each
     *
     * @return $this
     */
    public function builderSlug(Closure $callback, Closure|null $each = null, string $message = 'validation.invalid'): static
    {
        return $this->builderRouteKey($callback, $each, $message);
    }

    /**
     * Add not builder slug rule.
     *
     * @template T of Builder
     *
     * @param Closure(mixed=, mixed=): T $callback
     * @param (Closure(Model, mixed=, mixed=): bool)|null $each
     *
     * @return $this
     */
    public function notBuilderSlug(Closure $callback, Closure|null $each = null, string $message = 'validation.invalid'): static
    {
        return $this->notBuilderRouteKey($callback, $each, $message);
    }

    /**
     * Add existing id rule.
     *
     * @template T of Builder
     *
     * @param Closure(mixed=, mixed=): T $callback
     * @param (Closure(Model, mixed=, mixed=): bool)|null $each
     *
     * @return $this
     */
    public function existingId(Closure $callback, Closure|null $each = null, string $message = 'validation.invalid'): static
    {
        return $this->builderKey($callback, $each, $message);
    }

    /**
     * Add not existing id rule.
     *
     * @template T of Builder
     *
     * @param Closure(mixed=, mixed=): T $callback
     * @param (Closure(Model, mixed=, mixed=): bool)|null $each
     *
     * @return $this
     */
    public function notExistingId(Closure $callback, Closure|null $each = null, string $message = 'validation.invalid'): static
    {
        return $this->notBuilderKey($callback, $each, $message);
    }

    /**
     * Add existing slug rule.
     *
     * @template T of Builder
     *
     * @param Closure(mixed=, mixed=): T $callback
     * @param (Closure(Model, mixed=, mixed=): bool)|null $each
     *
     * @return $this
     */
    public function existingSlug(Closure $callback, Closure|null $each = null, string $message = 'validation.invalid'): static
    {
        return $this->builderRouteKey($callback, $each, $message);
    }

    /**
     * Add not existing slug rule.
     *
     * @template T of Builder
     *
     * @param Closure(mixed=, mixed=): T $callback
     * @param (Closure(Model, mixed=, mixed=): bool)|null $each
     *
     * @return $this
     */
    public function notExistingSlug(Closure $callback, Closure|null $each = null, string $message = 'validation.invalid'): static
    {
        return $this->notBuilderRouteKey($callback, $each, $message);
    }

    /**
     * Add builder id/slug rule.
     *
     * @template T of Builder
     *
     * @param Closure(mixed=, mixed=): T $callback
     * @param (Closure(Model, mixed=, mixed=): (bool|int|null))|null $each
     *
     * @return $this
     */
    public function builderIdSlug(Closure $callback, Closure|null $each = null, string $message = 'validation.invalid'): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule(
            new CallbackRule(static function (mixed $value, mixed $attribute = null) use ($callback, $each): bool|int {
                $builder = $callback($value, $attribute);

                $model = Typer::assertInstance($builder->getModel(), Model::class);

                $builder->where(static function (Builder $builder) use ($value, $model): void {
                    $builder->whereKey($value)->orWhere($model->getRouteKeyName(), $value);
                });

                $exists = $builder->toBase()->exists();

                if (!$exists) {
                    return false;
                }

                if ($each === null) {
                    return true;
                }

                foreach ($builder->cursor() as $found) {
                    $ok = $each(Typer::assertInstance($found, Model::class), $value, $attribute);

                    if ($ok === null) {
                        continue;
                    }

                    return $ok;
                }

                return true;
            }, $message),
        );
    }

    /**
     * Add not builder id/slug rule.
     *
     * @template T of Builder
     *
     * @param Closure(mixed=, mixed=): T $callback
     * @param (Closure(Model, mixed=, mixed=): (bool|int|null))|null $each
     *
     * @return $this
     */
    public function notBuilderIdSlug(Closure $callback, Closure|null $each = null, string $message = 'validation.invalid'): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule(
            new CallbackRule(static function (mixed $value, mixed $attribute = null) use ($callback, $each): bool|int {
                $builder = $callback($value, $attribute);

                $model = Typer::assertInstance($builder->getModel(), Model::class);

                $builder->where(static function (Builder $builder) use ($value, $model): void {
                    $builder->whereKey($value)->orWhere($model->getRouteKeyName(), $value);
                });

                $exists = $builder->toBase()->exists();

                if (!$exists) {
                    return true;
                }

                if ($each === null) {
                    return false;
                }

                foreach ($builder->cursor() as $found) {
                    $ok = $each(Typer::assertInstance($found, Model::class), $value, $attribute);

                    if ($ok === null) {
                        continue;
                    }

                    return $ok;
                }

                return false;
            }, $message),
        );
    }

    /**
     * Add existing id/slug rule.
     *
     * @template T of Builder
     *
     * @param Closure(mixed=, mixed=): T $callback
     * @param (Closure(Model, mixed=, mixed=): (bool|int|null))|null $each
     *
     * @return $this
     */
    public function existingIdSlug(Closure $callback, Closure|null $each = null, string $message = 'validation.invalid'): static
    {
        return $this->builderIdSlug($callback, $each, $message);
    }

    /**
     * Add not existing id/slug rule.
     *
     * @template T of Builder
     *
     * @param Closure(mixed=, mixed=): T $callback
     * @param (Closure(Model, mixed=, mixed=): (bool|int|null))|null $each
     *
     * @return $this
     */
    public function notExistingIdSlug(Closure $callback, Closure|null $each = null, string $message = 'validation.invalid'): static
    {
        return $this->notBuilderIdSlug($callback, $each, $message);
    }
}
