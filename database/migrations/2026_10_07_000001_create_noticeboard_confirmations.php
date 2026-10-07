<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Schedule cards and preserve the first arrival's daily reading list.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->table('noticeboard_cards', static function (Blueprint $table): void {
            $table->date('display_on')->nullable();
            $table->index(['store_id', 'display_on']);
        });

        Resolver::resolveSchemaBuilder()->create('noticeboard_confirmations', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('attendance_session_id')->constrained('attendance_sessions')->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained('workers')->restrictOnDelete();
            $table->string('worker_name');
            $table->date('date');
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->unique(['store_id', 'date']);
        });

        Resolver::resolveSchemaBuilder()->create('noticeboard_confirmation_items', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('confirmation_id')->constrained('noticeboard_confirmations')->cascadeOnDelete();
            $table->foreignId('noticeboard_card_id')->nullable()->constrained('noticeboard_cards')->nullOnDelete();
            $table->unsignedInteger('card_version');
            $table->text('body_html');
            $table->string('label', 32);
            $table->string('color', 32);
            $table->string('size', 16);
            $table->string('image_path')->nullable()->index();
            $table->string('image_mime', 64)->nullable();
            $table->unsignedInteger('position');
            $table->timestamps();
            $table->unique(['confirmation_id', 'noticeboard_card_id'], 'noticeboard_confirmation_items_card_unique');
        });
    }
};
