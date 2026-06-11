<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Notifications\AnonymousNotifiable;
use Thinkycz\LaravelCore\Notifications\TestNotification;
use Thinkycz\LaravelCore\Support\Resolver;

class TestMailCommand extends Command
{
    /**
     * @inheritDoc
     */
    protected $signature = 'test:mail';

    /**
     * @inheritDoc
     */
    protected $description = 'Test mail command';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $mail = \filter_var($this->ask('Mail to send test mail: '), \FILTER_VALIDATE_EMAIL);

        if ($mail === false) {
            $this->error('Given data is not valid e-mail address.');

            return 1;
        }

        $target = (new AnonymousNotifiable())->route('mail', $mail);

        Resolver::resolveNotificationFactory()->sendNow($target, new TestNotification());

        $this->info("Test notification sent to: [{$mail}].");

        return 0;
    }
}
