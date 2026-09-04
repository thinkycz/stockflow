<?php

declare(strict_types=1);

use App\Console\Commands\AdminBootstrapCommand;
use App\Console\Commands\AuditIntegrityCommand;
use App\Console\Commands\BackfillInventoryConsumptionCommand;
use App\Console\Commands\DiagnoseAssistantCommand;
use App\Console\Commands\GenerateDailyChecklistsCommand;
use App\Console\Commands\IdentityReadinessCommand;
use App\Console\Commands\PruneNoticeboardCardsCommand;
use App\Http\Middleware\EnsureApiCookieCsrf;
use App\Http\Middleware\EnsureLimitedUserCanAccessSection;
use App\Http\Middleware\EnsureLimitedUserCanAccessStockMovementSection;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveActiveStore;
use App\Jobs\CreateDailyOperationalDigestJob;
use App\Jobs\MaintainAssistantTurnsJob;
use App\Jobs\MaintainBankStatementImportsJob;
use App\Jobs\PruneAssistantActionAuditsJob;
use App\Jobs\PruneOperationalDigestHistoryJob;
use App\Jobs\RecordAssistantQueueHeartbeatJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thinkycz\LaravelCore\Exceptions\Handler;
use Thinkycz\LaravelCore\Http\Middleware\AuthShouldUseMiddleware;
use Thinkycz\LaravelCore\Http\Middleware\SetPreferredLanguageMiddleware;
use Thinkycz\LaravelCore\Http\Middleware\SetRequestFormatMiddleware;
use Thinkycz\LaravelCore\Http\Middleware\ValidateAcceptHeaderMiddleware;
use Thinkycz\LaravelCore\Http\Middleware\ValidateContentTypeHeaderMiddleware;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Env;
use Thinkycz\LaravelCore\Support\Resolver;

return Application::configure(basePath: \dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(static function (Middleware $middleware): void {
        $middleware->trustProxies(at: Env::inject()->parseNullableString('TRUSTED_PROXIES'));
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/dashboard');

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'limited-section' => EnsureLimitedUserCanAccessSection::class,
            'limited-stock-movement' => EnsureLimitedUserCanAccessStockMovementSection::class,
        ]);

        $middleware->web(append: [
            AuthShouldUseMiddleware::class,
            SetPreferredLanguageMiddleware::class,
            ResolveActiveStore::class,
            HandleInertiaRequests::class,
        ]);

        $middleware->api(append: [
            AuthShouldUseMiddleware::class,
            SetPreferredLanguageMiddleware::class,
            AddQueuedCookiesToResponse::class,
            SetRequestFormatMiddleware::class . ':json',
            ValidateAcceptHeaderMiddleware::class . ':application/vnd.api+json,application/json',
            ValidateContentTypeHeaderMiddleware::class . ':form,json',
            EnsureApiCookieCsrf::class,
        ]);
    })
    ->withSingletons([
        ExceptionHandler::class => Handler::class,
    ])
    ->withCommands([
        BackfillInventoryConsumptionCommand::class,
        AdminBootstrapCommand::class,
        AuditIntegrityCommand::class,
        DiagnoseAssistantCommand::class,
        GenerateDailyChecklistsCommand::class,
        IdentityReadinessCommand::class,
        PruneNoticeboardCardsCommand::class,
    ])
    ->withSchedule(static function (Schedule $schedule): void {
        $config = Config::inject();

        $timezone = $config->assertString('app.schedule_timezone');

        foreach ($config->assertArray('auth.passwords') as $passwordBrokerName => $passwordBrokerConfig) {
            $schedule
                ->command("auth:clear-resets {$passwordBrokerName}")
                ->dailyAt('04:00')
                ->timezone($timezone)
                ->runInBackground();
        }

        $schedule
            ->command('cache:prune-stale-tags')
            ->hourly();

        $schedule
            ->command('stockflow:prune-noticeboard-cards')
            ->dailyAt('03:30')
            ->timezone($timezone)
            ->runInBackground();

        $schedule
            ->command('stockflow:generate-daily-checklists')
            ->dailyAt('00:05')
            ->timezone($timezone)
            ->runInBackground();

        $schedule
            ->job(new CreateDailyOperationalDigestJob())
            ->hourly()
            ->between('07:00', '23:00')
            ->timezone($timezone)
            ->withoutOverlapping()
            ->onOneServer();

        $schedule
            ->job(new PruneOperationalDigestHistoryJob())
            ->dailyAt('04:15')
            ->timezone($timezone)
            ->withoutOverlapping()
            ->onOneServer();

        $schedule
            ->job(new PruneAssistantActionAuditsJob())
            ->dailyAt('04:30')
            ->timezone($timezone)
            ->withoutOverlapping()
            ->onOneServer();

        $schedule
            ->job(new MaintainAssistantTurnsJob())
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->onOneServer();

        $schedule
            ->job(new MaintainBankStatementImportsJob())
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->onOneServer();

        $schedule
            ->job(new RecordAssistantQueueHeartbeatJob())
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer();
    })
    ->withExceptions(static function (Exceptions $exceptions): void {
        $exceptions->map(
            ModelNotFoundException::class,
            static fn(ModelNotFoundException $exception): NotFoundHttpException => new NotFoundHttpException($exception->getMessage(), $exception),
        );

        $exceptions->render(static function (ValidationException $exception) {
            // No $request parameter: the Laravel test framework's own
            // render callback references an Illuminate\Contracts\Http\Request
            // type that doesn't exist in this Laravel version, and having
            // our closure also typed as Illuminate\Http\Request confuses
            // the handler's reflection-based dispatch. The request() helper
            // returns the same instance.
            if (\request()->header('X-Inertia') !== 'true') {
                return;
            }

            $request = \request();

            if ($request->header('X-StockFlow-Action') === 'true') {
                foreach (Arr::flatten($exception->errors()) as $message) {
                    if (\is_string($message) && $message !== '') {
                        Inertia::flash('error', $message);

                        break;
                    }
                }
            }

            return Resolver::resolveRedirector()
                ->to($exception->redirectTo ?? Resolver::resolveUrlGenerator()->previous())
                ->withInput($request->except([
                    'current_password',
                    'current_password_confirmation',
                    'password',
                    'password_confirmation',
                    'new_password',
                    'new_password_confirmation',
                ]))
                ->withErrors(
                    $exception->errors(),
                    $exception->errorBag === '' ? 'default' : $exception->errorBag,
                );
        });
    })
    ->create();
