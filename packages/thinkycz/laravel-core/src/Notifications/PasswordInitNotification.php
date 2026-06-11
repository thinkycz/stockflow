<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue as ShouldQueueContract;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Trans;
use Thinkycz\LaravelCore\Support\Typer;

class PasswordInitNotification extends Notification implements ShouldQueueContract
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
            ->subject($trans->assertString('thinkycz::notifications.password_init.subject'))
            ->line($trans->assertString('thinkycz::notifications.password_init.line1'))
            ->action($trans->assertString('thinkycz::notifications.password_init.action'), $this->getUrl($notifiable))
            ->line(
                $trans->assertString('thinkycz::notifications.password_init.line2', [
                    'count' => (string) $config->assertInt("auth.passwords.{$this->guardName}.expire"),
                ]),
            )
            ->line($trans->assertString('thinkycz::notifications.password_init.line3'));
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

        return ($this->spa ?? Trans::inject()->assertString('spa.password_init_url')) . '?' . $query;
    }
}
