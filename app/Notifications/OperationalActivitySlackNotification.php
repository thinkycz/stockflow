<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\OperationalActivityTypeEnum;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ContextBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class OperationalActivitySlackNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Activity type.
     */
    private readonly OperationalActivityTypeEnum $type;

    /**
     * Acting user's email address.
     */
    private readonly string $actorEmail;

    /**
     * Store name for this destination.
     */
    private readonly string|null $storeName;

    /**
     * Transfer perspective, when applicable.
     */
    private readonly string|null $perspective;

    /**
     * ISO-8601 event timestamp.
     */
    private readonly string $occurredAt;

    /**
     * @var array<string, string>
     */
    private readonly array $facts;

    /**
     * Stable application URL.
     */
    private readonly string $url;

    /**
     * Create a queued scalar notification snapshot.
     *
     * @param array<string, string> $facts
     */
    public function __construct(
        OperationalActivityTypeEnum $type,
        string $actorEmail,
        string|null $storeName,
        string|null $perspective,
        string $occurredAt,
        array $facts,
        string $url,
    ) {
        $this->type = $type;
        $this->actorEmail = $actorEmail;
        $this->storeName = $storeName;
        $this->perspective = $perspective;
        $this->occurredAt = $occurredAt;
        $this->facts = $facts;
        $this->url = $url;
        $this->afterCommit();
    }

    /**
     * Delivery channels.
     *
     * @return list<string>
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
        $title = $this->translate($this->type->translationKey());
        $time = CarbonImmutable::parse($this->occurredAt)
            ->setTimezone('Europe/Prague')
            ->format('j. n. Y H:i');

        $message = (new SlackMessage())
            ->text($this->storeName === null ? $title : $title . ': ' . $this->storeName)
            ->headerBlock($title)
            ->sectionBlock(function (SectionBlock $block) use ($time): void {
                if ($this->storeName !== null) {
                    $block->field('*' . $this->translate('Slack store') . ":*\n" . $this->escape($this->storeName))->markdown();
                }

                $block->field('*' . $this->translate('Slack actor') . ":*\n" . $this->escape($this->actorEmail))->markdown();
                $block->field('*' . $this->translate('Slack time') . ":*\n" . $time)->markdown();

                if ($this->perspective !== null) {
                    $block->field('*' . $this->translate('Slack direction') . ":*\n" . $this->translate('Slack direction ' . $this->perspective))->markdown();
                }
            });

        foreach (\array_chunk($this->facts, 10, true) as $facts) {
            $message->sectionBlock(function (SectionBlock $block) use ($facts): void {
                foreach ($facts as $label => $value) {
                    $block->field('*' . $this->translate($label) . ":*\n" . $this->escape($value))->markdown();
                }
            });
        }

        return $message->contextBlock(function (ContextBlock $block): void {
            $block->text('<' . $this->url . '|' . $this->translate('Open in StockFlow') . '>')->markdown();
        });
    }

    /**
     * Store name captured for this destination.
     */
    public function getStoreName(): string|null
    {
        return $this->storeName;
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
