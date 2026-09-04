<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fence legacy jobs until active imports are explicitly requeued.
     */
    public function up(): void
    {
        Schema::table('bank_statements', static function (Blueprint $table): void {
            $table->unsignedBigInteger('parse_generation')->default(0);
        });
    }
};
