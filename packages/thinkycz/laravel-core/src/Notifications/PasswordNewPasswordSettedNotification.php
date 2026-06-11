<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue as ShouldQueueContract;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Trans;

class PasswordNewPasswordSettedNotification extends Notification implements ShouldQueueContract
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    protected function __construct(
        protected string $password,
    ) {
        $this->afterCommit();
    }

    /**
     * Inject.
     */
    public static function inject(string $password): self
    {
        return new self($password);
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
            ->subject($trans->assertString('thinkycz::notifications.password_new_password_setted.subject'))
            ->line(
                $trans->assertString('thinkycz::notifications.password_new_password_setted.line1', [
                    'password' => $this->password,
                ]),
            )
            ->line($trans->assertString('thinkycz::notifications.password_new_password_setted.line2'));
    }
}
