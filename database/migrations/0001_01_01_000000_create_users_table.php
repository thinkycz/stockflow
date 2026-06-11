<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->create('users', static function (Blueprint $table): void {
            $table->id();

            $table->string('email')->unique();

            $table->timestamp('email_verified_at')->nullable();

            $table->string('password');

            $table->string('locale');

            $table->rememberToken();

            $table->timestamps();
        });

        Resolver::resolveSchemaBuilder()->create('user_password_resets', static function (Blueprint $table): void {
            $table->string('email')->primary();

            $table->string('token');

            $table->timestamp('created_at')->nullable();
        });

        Resolver::resolveSchemaBuilder()->create('database_tokens', static function (Blueprint $table): void {
            $table->id();

            $table->char('hash', 64);

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->timestamp('created_at');
            $table->timestamp('updated_at');
        });
    }
};
