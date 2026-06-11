<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Http;

use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Thinkycz\LaravelCore\Support\Hash;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class RequestSignature
{
    /**
     * Data.
     *
     * @var array<string, string>
     */
    public array $data = [];

    /**
     * Constructor.
     */
    public function __construct(public Request $request) {}

    /**
     * Default constructor.
     */
    public static function default(string $key = ''): self
    {
        return (new self(Resolver::resolveRequest()))->defaults()->key($key);
    }

    /**
     * Default setup.
     */
    public function defaults(): static
    {
        return $this->auth()->ip()->action();
    }

    /**
     * Add auth to request signature.
     *
     * @return $this
     */
    public function auth(string|null $guard = null): static
    {
        return $this->user(Typer::assertNullableInstance($this->request->user($guard), AuthenticatableContract::class));
    }

    /**
     * Add user to request signature.
     *
     * @return $this
     */
    public function user(AuthenticatableContract|null $auth): static
    {
        if ($auth === null) {
            return $this->data('user', '');
        }

        return $this->data('user', \sprintf('%s:%s', $auth::class, (string) Typer::assertNullableScalar($auth->getAuthIdentifier())));
    }

    /**
     * Add ip to request signature.
     *
     * @return $this
     */
    public function ip(): static
    {
        return $this->data('ip', $this->request->ip() ?? '');
    }

    /**
     * Add action to request signature.
     *
     * @return $this
     */
    public function action(): static
    {
        return $this->data('action', Typer::assertInstance($this->request->route(), Route::class)->getActionName());
    }

    /**
     * Add locale to request signature.
     *
     * @return $this
     */
    public function locale(): static
    {
        return $this->data('locale', $this->request->getLocale());
    }

    /**
     * Add query to request signature.
     *
     * @return $this
     */
    public function query(): static
    {
        return $this->data('query', $this->request->getQueryString() ?? '');
    }

    /**
     * Add path to request signature.
     *
     * @return $this
     */
    public function path(): static
    {
        return $this->data('path', $this->request->path());
    }

    /**
     * Add key to request signature.
     *
     * @return $this
     */
    public function key(string $key): static
    {
        return $this->data('key', $key);
    }

    /**
     * Add data to request signature.
     *
     * @return $this
     */
    public function data(string $key, string $data): static
    {
        $this->data[$key] = $data;

        return $this;
    }

    /**
     * Make request signature hash.
     */
    public function hash(): string
    {
        return Hash::encode($this->data);
    }
}
