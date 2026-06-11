<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Providers;

use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Validation\Factory as ValidationFactoryContract;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;
use Thinkycz\LaravelCore\Commands\MakeCrudCommand;
use Thinkycz\LaravelCore\Commands\MakeCrudDestroyCommand;
use Thinkycz\LaravelCore\Commands\MakeCrudIndexCommand;
use Thinkycz\LaravelCore\Commands\MakeCrudRoutesCommand;
use Thinkycz\LaravelCore\Commands\MakeCrudShowCommand;
use Thinkycz\LaravelCore\Commands\MakeCrudStoreCommand;
use Thinkycz\LaravelCore\Commands\MakeCrudUpdateCommand;
use Thinkycz\LaravelCore\Commands\MakeEnumCommand;
use Thinkycz\LaravelCore\Commands\MakeTestDestroyCommand;
use Thinkycz\LaravelCore\Commands\MakeTestIndexCommand;
use Thinkycz\LaravelCore\Commands\MakeTestShowCommand;
use Thinkycz\LaravelCore\Commands\MakeTestStoreCommand;
use Thinkycz\LaravelCore\Commands\MakeTestUpdateCommand;
use Thinkycz\LaravelCore\Commands\MakeValidityCommand;
use Thinkycz\LaravelCore\Commands\TestMailCommand;
use Thinkycz\LaravelCore\Guards\DatabaseTokenGuard;
use Thinkycz\LaravelCore\Validation\Validator;

class CoreServiceProvider extends IlluminateServiceProvider
{
    /**
     * @inheritDoc
     */
    public function register(): void
    {
        parent::register();

        $this->registerValidator();

        $this->registerDatabaseTokenGuard();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../../lang', 'thinkycz');

        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'thinkycz');

        $this->registerCommands();
    }

    /**
     * Register validator.
     */
    protected function registerValidator(): void
    {
        $this->app->afterResolving('validator', static function (ValidationFactoryContract $factory): void {
            Validator::extend($factory, Validator::class);
        });
    }

    /**
     * Register database token guard.
     */
    protected function registerDatabaseTokenGuard(): void
    {
        $this->app->afterResolving('auth', static function (AuthManager $authManager): void {
            $authManager->extend('database_token', function (Application $app, string $name): DatabaseTokenGuard {
                return new DatabaseTokenGuard($name);
            });
        });
    }

    /**
     * Register commands.
     */
    protected function registerCommands(): void
    {
        $this->commands([
            MakeValidityCommand::class,
            MakeEnumCommand::class,
            MakeCrudIndexCommand::class,
            MakeCrudShowCommand::class,
            MakeCrudStoreCommand::class,
            MakeCrudUpdateCommand::class,
            MakeCrudDestroyCommand::class,
            MakeTestIndexCommand::class,
            MakeTestShowCommand::class,
            MakeTestStoreCommand::class,
            MakeTestUpdateCommand::class,
            MakeTestDestroyCommand::class,
            MakeCrudRoutesCommand::class,
            MakeCrudCommand::class,
            TestMailCommand::class,
        ]);
    }
}
