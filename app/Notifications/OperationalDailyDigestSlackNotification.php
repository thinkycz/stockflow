<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\OperationalDailyDigest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ContextBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;
use Throwable;

class OperationalDailyDigestSlackNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Maximum delivery attempts.
     */
    public int $tries = 5;

    /**
     * Create a queued daily digest notification.
     */
    public function __construct(private readonly int $digestId)
    {
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
     * Build the Slack payload.
     */
    public function toSlack(object $notifiable): SlackMessage
    {
        $digest = OperationalDailyDigest::query()->findOrFail($this->digestId);
        $digest->incrementAttemptCount();
        $snapshot = $digest->getSnapshot();
        $title = Typer::assertString($snapshot['title'] ?? null);
        $intro = Typer::assertString($snapshot['intro'] ?? null);
        $archiveUrl = Resolver::resolveUrlGenerator()->to('/settings/slack-digests/' . $digest->getKey());

        $message = (new SlackMessage())
            ->text($title)
            ->headerBlock($title)
            ->sectionBlock(static function (SectionBlock $block) use ($intro): void {
                $block->text($intro)->markdown();
            });

        foreach ($this->sectionChunks($snapshot) as $chunk) {
            $message->sectionBlock(static function (SectionBlock $block) use ($chunk): void {
                $block->text($chunk)->markdown();
            });
        }

        return $message->contextBlock(static function (ContextBlock $block) use ($archiveUrl): void {
            $block->text('<' . $archiveUrl . '|Otevřít úplný souhrn ve StockFlow>')->markdown();
        });
    }

    /**
     * Retry delays in seconds.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 1800, 18000];
    }

    /**
     * Mark a successful Slack channel response.
     */
    public function markSent(): void
    {
        $digest = OperationalDailyDigest::query()->find($this->digestId);
        if ($digest instanceof OperationalDailyDigest) {
            $digest->markSent();
        }
    }

    /**
     * Mark exhausted queue retries without persisting transport secrets.
     */
    public function failed(Throwable $exception): void
    {
        $digest = OperationalDailyDigest::query()->find($this->digestId);
        if ($digest instanceof OperationalDailyDigest) {
            $digest->markFailed('Slack doručení selhalo po vyčerpání retry.');
        }
    }

    /**
     * Pack human text into a bounded number of Slack section blocks.
     *
     * @param array<string, mixed> $snapshot
     *
     * @return list<string>
     */
    private function sectionChunks(array $snapshot): array
    {
        $chunks = [];
        $current = '';
        foreach (Typer::assertArray($snapshot['sections'] ?? null) as $value) {
            $section = Typer::assertStringKeyArray(Typer::assertArray($value));
            $lines = ['*' . $this->escape(Typer::assertString($section['name'] ?? null)) . '*'];
            foreach (Typer::assertArray($section['paragraphs'] ?? null) as $paragraph) {
                $lines[] = $this->escape(Typer::assertString($paragraph));
            }
            $text = \implode("\n", $lines);

            if ($current !== '' && \mb_strlen($current . "\n\n" . $text) > 2800) {
                $chunks[] = $current;
                $current = '';
            }
            if (\count($chunks) >= 44) {
                $chunks[43] = \mb_substr($chunks[43] . "\n\nDalší podrobnosti jsou v archivu.", 0, 2800);

                break;
            }
            $current = $current === '' ? $text : $current . "\n\n" . $text;
        }

        if ($current !== '' && \count($chunks) < 44) {
            $chunks[] = \mb_substr($current, 0, 2800);
        }

        return \array_values($chunks);
    }

    /**
     * Escape user-controlled text for Slack mrkdwn.
     */
    private function escape(string $value): string
    {
        return \str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $value);
    }
}
