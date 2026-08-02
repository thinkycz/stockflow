<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Create gift-voucher settings, batches, vouchers, and audit events.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->create('gift_voucher_settings', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('public_name', 120);
            $table->string('message', 240)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('logo_mime', 100)->nullable();
            $table->timestamps();
        });

        Resolver::resolveSchemaBuilder()->create('gift_voucher_batches', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('quantity');
            $table->decimal('amount', 12, 2);
            $table->timestamp('expires_at')->nullable();
            $table->string('brand_name', 120);
            $table->string('brand_message', 240)->nullable();
            $table->string('brand_logo_path')->nullable();
            $table->string('brand_logo_mime', 100)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Resolver::resolveSchemaBuilder()->create('gift_vouchers', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gift_voucher_batch_id')->constrained('gift_voucher_batches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('code');
            $table->char('code_hash', 64)->unique();
            $table->string('status', 16)->default('active');
            $table->timestamp('redeemed_at')->nullable();
            $table->foreignId('redeemed_store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignId('redeemed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['gift_voucher_batch_id', 'status']);
        });

        Resolver::resolveSchemaBuilder()->create('gift_voucher_events', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gift_voucher_id')->constrained('gift_vouchers')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('type', 32);
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->index(['gift_voucher_id', 'created_at']);
        });
    }
};
