<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;
use Thinkycz\LaravelCore\Validation\AuthValidity;

final class AdminBootstrapCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'stockflow:admin:bootstrap {email : Main administrator email} {--rotate : Rotate the matching administrator password}';

    /**
     * @var string
     */
    protected $description = 'Securely provision or explicitly rotate the single StockFlow administrator.';

    /**
     * Provision the administrator or rotate its password.
     */
    public function handle(): int
    {
        $email = Typer::assertString($this->argument('email'));

        if (false === \filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            $this->error(Typer::assertString(\__('A valid administrator email is required.')));

            return self::FAILURE;
        }

        try {
            $existing = $this->resolveAdmin($email);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($existing instanceof User && $this->option('rotate') !== true) {
            DB::transaction(static function () use ($existing): void {
                $existing->provisionWarehouse();
            }, 3);
            $this->info(Typer::assertString(\__('Main administrator already provisioned; no changes made.')));

            return self::SUCCESS;
        }

        $password = $this->promptForPassword();

        if ($password === null) {
            return self::FAILURE;
        }

        try {
            $rotated = DB::transaction(function () use ($email, $password): bool {
                $admin = $this->resolveAdmin($email, true);

                if ($admin instanceof User) {
                    $admin->update(['password' => $password]);
                    $admin->databaseTokens()->getQuery()->delete();
                    $admin->provisionWarehouse();

                    return true;
                }

                $admin = User::query()->create([
                    'email' => $email,
                    'email_verified_at' => CarbonImmutable::now(),
                    'password' => $password,
                    'locale' => Config::inject()->appLocale(),
                    'remember_token' => null,
                    'is_admin' => true,
                    'parent_user_id' => null,
                    'assigned_store_id' => null,
                    'operational_digest_started_on' => CarbonImmutable::now('Europe/Prague')->toDateString(),
                ]);
                $admin->provisionWarehouse();

                return false;
            }, 3);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(Typer::assertString($rotated ? \__('Administrator password rotated.') : \__('Main administrator created.')));

        return self::SUCCESS;
    }

    /**
     * Validate the single-company identity state and resolve its administrator.
     */
    private function resolveAdmin(string $email, bool $lock = false): User|null
    {
        $query = User::query();
        User::scopeAdmin($query);
        $admins = ($lock ? $query->lockForUpdate() : $query)->get();

        if ($admins->count() > 1) {
            throw new RuntimeException(Typer::assertString(\__('StockFlow supports exactly one main administrator.')));
        }

        $orphanQuery = User::query()
            ->where('is_admin', false)
            ->whereNull('parent_user_id');
        if ($lock) {
            $orphanQuery->lockForUpdate();
        }

        if ($orphanQuery->exists()) {
            throw new RuntimeException(Typer::assertString(\__('Cannot bootstrap while orphan root users exist.')));
        }

        $admin = $admins->first();
        if ($admin instanceof User) {
            if ($email !== $admin->getEmail()) {
                throw new RuntimeException(Typer::assertString(\__('The existing main administrator does not match the requested email.')));
            }

            return $admin;
        }

        $users = User::query();
        if ($lock) {
            $users->lockForUpdate();
        }

        if ($users->getQuery()->exists()) {
            throw new RuntimeException(Typer::assertString(\__('Cannot bootstrap the main administrator while user records already exist.')));
        }

        return null;
    }

    /**
     * Prompt for a validated password without exposing it in process arguments or output.
     */
    private function promptForPassword(): string|null
    {
        $password = $this->secret(Typer::assertString(\__('Administrator password')));
        $confirmation = $this->secret(Typer::assertString(\__('Confirm administrator password')));

        $validator = Resolver::resolveValidator([
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'password' => AuthValidity::inject()->password()->required()->confirmed()->toArray(),
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return null;
        }

        return Typer::assertString($password);
    }
}
