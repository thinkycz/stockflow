<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ai\Tools\ReadStoresTool;
use App\Enums\AssistantTurnStatusEnum;
use App\Models\AssistantTurn;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Carbon;
use Laravel\Ai\Tools\Request;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;
use Throwable;

final class DiagnoseAssistantCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'stockflow:assistant:diagnose {--live : Run a tenant-scoped read-only data smoke test}';

    /**
     * @var string
     */
    protected $description = 'Check AI assistant configuration, storage, Redis locking, queue heartbeat, and stale turns.';

    /**
     * Run assistant deployment diagnostics and an optional safe live read.
     */
    public function handle(): int
    {
        $failed = false;
        $config = Config::inject();

        $this->check('Assistant enabled', $config->assertBool('ai.assistant.enabled'), $failed);
        $this->check('OpenRouter API key configured', $config->assertNullableString('ai.providers.openrouter.key') !== null, $failed);
        $this->check('Provider is OpenRouter', $config->assertString('ai.default') === 'openrouter', $failed);
        $this->check('Model is minimax/minimax-m3:free', $config->assertString('ai.providers.openrouter.models.text.default') === 'minimax/minimax-m3:free', $failed);

        $schema = Resolver::resolveSchemaBuilder();
        foreach (['agent_conversations', 'agent_conversation_messages', 'assistant_turns', 'assistant_turn_events', 'assistant_conversation_summaries', 'assistant_action_audits'] as $table) {
            $this->check("Table {$table}", $schema->hasTable($table), $failed);
        }
        $this->check('Turn retry columns', $schema->hasColumns('assistant_turns', ['parent_turn_id', 'recovery_mode']), $failed);
        $this->check('Audit turn correlation', $schema->hasColumn('assistant_action_audits', 'turn_id'), $failed);

        try {
            $store = Resolver::resolveCacheManager()
                ->store($config->assertString('ai.assistant.lock_store'))
                ->getStore();
            $locked = false;
            if ($store instanceof LockProvider) {
                $lock = $store->lock('assistant:diagnose:' . \getmypid(), 10);
                $locked = $lock->get() === true;
                if ($locked) {
                    $lock->release();
                }
            }
            $this->check('Atomic conversation locks', $locked, $failed);
        } catch (Throwable) {
            $this->check('Atomic conversation locks', false, $failed);
        }

        $retryAfter = $config->assertInt('queue.connections.assistant.retry_after');
        $this->check('Assistant queue retry_after exceeds generation timeout', $retryAfter > $config->assertInt('ai.assistant.timeout_seconds'), $failed);

        $heartbeat = Resolver::resolveCacheManager()
            ->store($config->assertString('ai.assistant.lock_store'))
            ->get('assistant:queue:heartbeat');
        $heartbeatIsFresh = \is_string($heartbeat) && Carbon::parse($heartbeat)->greaterThan(\now()->subMinutes(3));
        $this->check('Assistant queue heartbeat', $heartbeatIsFresh, $failed);

        $staleBefore = \now()->subSeconds($config->assertInt('ai.assistant.timeout_seconds') + 60);
        $staleTurns = AssistantTurn::query()
            ->whereIn('status', [
                AssistantTurnStatusEnum::QUEUED->value,
                AssistantTurnStatusEnum::RUNNING->value,
                AssistantTurnStatusEnum::CANCEL_REQUESTED->value,
            ])
            ->where('updated_at', '<', $staleBefore)
            ->count();
        $this->check('No stale durable turns', $staleTurns === 0, $failed, $staleTurns . ' stale');

        if ($this->option('live') === true) {
            $adminQuery = User::query();
            User::scopeAdmin($adminQuery);
            $admin = $adminQuery->first();
            if (!$admin instanceof User) {
                $this->check('Read-only smoke', false, $failed, 'no main administrator');
            } else {
                $decoded = \json_decode((new ReadStoresTool($admin, 'diagnostic-read-only'))->handle(
                    new Request(['request' => ['operation' => 'summary']]),
                ), true, flags: \JSON_THROW_ON_ERROR);
                $result = Typer::assertStringKeyArray(Typer::assertArray($decoded));
                $this->check('Read-only smoke', Typer::assertBool($result['ok'] ?? false), $failed);
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Render one diagnostic result and retain the aggregate failure state.
     */
    private function check(string $label, bool $passes, bool &$failed, string|null $detail = null): void
    {
        $message = ($passes ? '[OK] ' : '[FAIL] ') . $label . ($detail === null ? '' : ' — ' . $detail);
        $passes ? $this->line($message) : $this->error($message);
        $failed = $failed || !$passes;
    }
}
