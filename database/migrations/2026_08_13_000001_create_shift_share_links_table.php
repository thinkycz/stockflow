<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

return new class extends Migration {
    /**
     * Replace the single store token with independently revocable share links.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->create('shift_share_links', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('name', 100)->nullable();
            $table->string('token', 64)->unique();
            $table->timestamps();
            $table->unique(['store_id', 'name']);
        });

        foreach (DB::table('stores')->whereNotNull('shift_share_token')->get(['id', 'user_id', 'shift_share_token', 'created_at', 'updated_at']) as $store) {
            DB::table('shift_share_links')->insert([
                'user_id' => Typer::parseInt($store->user_id),
                'store_id' => Typer::parseInt($store->id),
                'name' => null,
                'token' => Typer::assertString($store->shift_share_token),
                'created_at' => $store->created_at,
                'updated_at' => $store->updated_at,
            ]);
        }

        Resolver::resolveSchemaBuilder()->table('stores', static function (Blueprint $table): void {
            $table->dropUnique(['shift_share_token']);
            $table->dropColumn('shift_share_token');
        });
    }
};
