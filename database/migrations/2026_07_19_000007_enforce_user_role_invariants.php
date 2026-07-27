<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Enforce the single-company role shape after the preflight conversion.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $invalid = DB::table('users')
            ->where(static function (Builder $query): void {
                $query->where(static function (Builder $admin): void {
                    $admin->where('is_admin', true)
                        ->where(static function (Builder $shape): void {
                            $shape->whereNotNull('parent_user_id')->orWhereNotNull('assigned_store_id');
                        });
                })->orWhere(static function (Builder $limited): void {
                    $limited->where('is_admin', false)
                        ->where(static function (Builder $shape): void {
                            $shape->whereNull('parent_user_id')->orWhereNull('assigned_store_id');
                        });
                });
            })
            ->count();

        if ($invalid > 0) {
            throw new RuntimeException(
                'Invalid user role rows found. Run stockflow:migrate-single-company --dry-run and then stockflow:migrate-single-company before this migration.',
            );
        }

        // MySQL does not allow a CHECK column to participate in an ON DELETE
        // SET NULL action. Restrict deletion instead, because either NULL
        // would leave a limited account in an invalid role shape.
        $this->replaceRoleForeignKeys(true);

        DB::statement(
            'ALTER TABLE users ADD CONSTRAINT users_role_shape_check CHECK ('
            . '(is_admin = 1 AND parent_user_id IS NULL AND assigned_store_id IS NULL) OR '
            . '(is_admin = 0 AND parent_user_id IS NOT NULL AND assigned_store_id IS NOT NULL)'
            . ')',
        );
    }

    /**
     * Remove the database invariant when rolling back in development.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE users DROP CHECK users_role_shape_check');
            $this->replaceRoleForeignKeys(false);
        }
    }

    /**
     * Switch role foreign keys between invariant-safe RESTRICT and the legacy
     * SET NULL behavior.
     */
    private function replaceRoleForeignKeys(bool $restrict): void
    {
        Resolver::resolveSchemaBuilder()->table('users', static function (Blueprint $table) use ($restrict): void {
            $table->dropForeign(['parent_user_id']);
            $table->dropForeign(['assigned_store_id']);

            $parent = $table->foreign('parent_user_id')->references('id')->on('users');
            $store = $table->foreign('assigned_store_id')->references('id')->on('stores');

            if ($restrict) {
                $parent->restrictOnDelete();
                $store->restrictOnDelete();

                return;
            }

            $parent->nullOnDelete();
            $store->nullOnDelete();
        });
    }
};
