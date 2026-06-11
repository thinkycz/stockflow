<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TrimStrings as Middleware;

class TrimStrings extends Middleware
{
    /**
     * @inheritDoc
     */
    protected $except = ['current_password', 'current_password_confirmation', 'password', 'password_confirmation', 'new_password', 'new_password_confirmation'];
}
