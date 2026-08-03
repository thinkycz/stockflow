<?php

declare(strict_types=1);

namespace App\Notifications;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ContextBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class SlackTestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public string $actorEmail)
    {
        $this->afterCommit();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['slack'];
    }

    /**
     * Build the Czech Block Kit message.
     */
    public function toSlack(object $notifiable): SlackMessage
    {
        $title = $this->translate('Slack test title');
        $time = CarbonImmutable::now()
            ->setTimezone('Europe/Prague')
            ->format('j. n. Y H:i');

        return (new SlackMessage())
            ->text($title)
            ->headerBlock($title)
            ->sectionBlock(function (SectionBlock $block) use ($time): void {
                $block->text('*' . $this->translate('Slack test body') . '*')->markdown();
                $block->field('*' . $this->translate('Slack test sent by') . ":*\n" . $this->escape($this->actorEmail))->markdown();
                $block->field('*' . $this->translate('Slack test sent at') . ":*\n" . $time)->markdown();
            })
            ->contextBlock(function (ContextBlock $block) use ($time): void {
                $block->text($this->translate('Slack test footer') . ' ' . $time)->markdown();
            });
    }

    /**
     * Translate fixed Slack copy into Czech.
     */
    private function translate(string $key): string
    {
        return Typer::assertString(Resolver::resolveTranslator()->get($key, [], 'cs'));
    }

    /**
     * Escape user-controlled text for Slack mrkdwn fields.
     */
    private function escape(string $value): string
    {
        return \str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $value);
    }
}
