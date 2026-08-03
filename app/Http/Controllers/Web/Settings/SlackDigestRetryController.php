<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Settings;

use App\Models\OperationalDailyDigest;
use App\Models\User;
use App\Services\DailyOperationalDigestService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class SlackDigestRetryController
{
    /**
     * Requeue one failed company digest.
     */
    public function __invoke(int $digest): RedirectResponse
    {
        $company = User::mustAuth();
        $model = Typer::assertInstance(OperationalDailyDigest::query()
            ->where('company_user_id', $company->getKey())
            ->whereKey($digest)
            ->firstOrFail(), OperationalDailyDigest::class);

        (new DailyOperationalDigestService())->retry($company, $model);
        Inertia::flash('success', \__('Daily Slack digest queued again.'));

        return Resolver::resolveRedirector()->route('settings.slack-digests.show', ['digest' => $model->getKey()]);
    }
}
