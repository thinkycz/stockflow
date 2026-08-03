<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Add the optional company-wide Slack destination.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->table('users', static function (Blueprint $table): void {
            $table->string('company_slack_channel', 100)->nullable();
        });
    }
};
