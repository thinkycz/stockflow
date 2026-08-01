<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Report;

use Illuminate\Http\RedirectResponse;
use Thinkycz\LaravelCore\Support\Resolver;

class StatisticsController
{
    /**
     * Redirect the retired statistics page to unified reports.
     */
    public function __invoke(): RedirectResponse
    {
        return Resolver::resolveRedirector()->route('reports.index');
    }
}
