<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lunar core's `lunar_product_types` table only has `id` + `name` + timestamps.
 * This package addresses product types by stable string handle, so we add the
 * column ourselves rather than asking every consumer to write a custom migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('lunar_product_types', 'handle')) {
            Schema::table('lunar_product_types', function (Blueprint $table) {
                $table->string('handle')->unique()->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('lunar_product_types', 'handle')) {
            Schema::table('lunar_product_types', function (Blueprint $table) {
                $table->dropColumn('handle');
            });
        }
    }
};
