<?php

declare(strict_types=1);

namespace App\Providers;

use App\Ai\AssistantConversationStore;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\Contracts\ConversationStore;
use Thinkycz\LaravelCore\Support\Config;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            ConversationStore::class,
            static fn(): AssistantConversationStore => new AssistantConversationStore(
                Config::inject()->assertNullableString('ai.conversations.connection'),
            ),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void {}
}
