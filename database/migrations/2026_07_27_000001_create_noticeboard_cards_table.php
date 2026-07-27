<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Create store-scoped noticeboard cards.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->create('noticeboard_cards', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 120);
            $table->text('body_html');
            $table->text('body_text');
            $table->string('label', 32);
            $table->string('color', 32);
            $table->string('image_path')->nullable();
            $table->string('image_mime', 64)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['store_id', 'deleted_at', 'expires_at', 'created_at'], 'noticeboard_cards_listing_index');
            $table->index(['user_id', 'store_id']);
        });
    }
};
