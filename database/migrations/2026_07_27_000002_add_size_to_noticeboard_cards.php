<?php

declare(strict_types=1);

use App\Enums\NoticeboardCardSizeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Add the user-selected display size to noticeboard cards.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->table('noticeboard_cards', static function (Blueprint $table): void {
            $table->string('size', 16)
                ->default(NoticeboardCardSizeEnum::Medium->value)
                ->after('color');
        });
    }
};
