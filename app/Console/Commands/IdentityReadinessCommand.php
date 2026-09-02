<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\StoreStatusEnum;
use App\Models\User;
use Illuminate\Console\Command;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class IdentityReadinessCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'stockflow:identity:diagnose';

    /**
     * @var string
     */
    protected $description = 'Verify the production single-company identity and bootstrap credential state.';

    /**
     * Run identity readiness checks.
     */
    public function handle(): int
    {
        $failed = false;
        $adminQuery = User::query();
        User::scopeAdmin($adminQuery);
        $admins = $adminQuery->get();
        $admin = $admins->count() === 1 ? $admins->first() : null;

        $this->check(Typer::assertString(\__('Exactly one main administrator')), $admin instanceof User, $failed);

        $orphanRoots = User::query()
            ->where('is_admin', false)
            ->whereNull('parent_user_id')
            ->count();
        $this->check(Typer::assertString(\__('No orphan root users')), $orphanRoots === 0, $failed);

        $demoCredentialRemoved = !$admin instanceof User ||
            $admin->getEmail() !== 'test@test.com' ||
            !Resolver::resolveHasher()->check('password', $admin->getAuthPassword());
        $this->check(Typer::assertString(\__('Demo credential removed')), $demoCredentialRemoved, $failed);

        $warehouseCount = $admin instanceof User
            ? $admin->stores()
                ->where('is_warehouse', true)
                ->where('status', StoreStatusEnum::ACTIVE->value)
                ->count()
            : 0;
        $this->check(Typer::assertString(\__('Exactly one active main warehouse')), $warehouseCount === 1, $failed);

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Render one readiness result and retain aggregate failure state.
     */
    private function check(string $label, bool $passes, bool &$failed): void
    {
        $message = ($passes ? '[OK] ' : '[FAIL] ') . $label;
        $passes ? $this->line($message) : $this->error($message);
        $failed = $failed || !$passes;
    }
}
