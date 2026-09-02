<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue as ShouldQueueContract;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Thinkycz\LaravelCore\Models\BaseUser;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Trans;
use Thinkycz\LaravelCore\Support\Typer;
use Throwable;

class PasswordResetNotification extends Notification implements ShouldQueueContract
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    protected function __construct(
        protected string $guardName,
        protected string|null $token = null,
        protected string|null $email = null,
        protected string|null $spa = null,
        protected string|null $url = null,
    ) {
        $this->afterCommit();
    }

    /**
     * Inject.
     */
    public static function inject(string $guardName, string|null $token = null, string|null $email = null, string|null $spa = null, string|null $url = null): self
    {
        return new self($guardName, $token, $email, $spa, $url);
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        $trans = Trans::inject();
        $config = Config::inject();

        return (new MailMessage())
            ->subject($trans->assertString('thinkycz::notifications.password_reset.subject'))
            ->line($trans->assertString('thinkycz::notifications.password_reset.line1'))
            ->action($trans->assertString('thinkycz::notifications.password_reset.action'), $this->getUrl($notifiable))
            ->line(
                $trans->assertString('thinkycz::notifications.password_reset.line2', [
                    'count' => (string) $config->assertInt("auth.passwords.{$this->guardName}.expire"),
                ]),
            )
            ->line($trans->assertString('thinkycz::notifications.password_reset.line3'));
    }

    /**
     * Remove the unusable reset token when queued delivery permanently fails.
     */
    public function failed(Throwable $exception): void
    {
        if ($this->email === null || $this->token === null) {
            return;
        }

        $config = Config::inject();
        $provider = $config->assertNullableString("auth.passwords.{$this->guardName}.provider")
            ?? $config->assertString('auth.defaults.provider');
        $user = Typer::assertNullableInstance(
            Resolver::resolveEloquentUserProvider($provider)->retrieveByCredentials(['email' => $this->email]),
            BaseUser::class,
        );

        if ($user instanceof BaseUser) {
            $broker = Resolver::resolvePasswordBroker($this->guardName);

            if ($broker->tokenExists($user, $this->token)) {
                $broker->deleteToken($user);
            }
        }
    }

    /**
     * Get url.
     */
    protected function getUrl(mixed $notifiable): string
    {
        if ($this->url !== null) {
            return $this->url;
        }

        Typer::assertTrue($this->token !== null && $this->email !== null && $this->locale !== null);

        $query = \http_build_query([
            'guard' => $this->guardName,
            'token' => $this->token,
            'email' => $this->email,
            'locale' => $this->locale,
        ]);

        $baseUrl = $this->spa ?? Resolver::resolveUrlGenerator()->route('reset-password.show');

        return Resolver::resolveUrlGenerator()->to($baseUrl) . '?' . $query;
    }
}
