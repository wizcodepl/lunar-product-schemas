<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracking table for `wizcodepl/lunar-product-schemas` definitions.
 * Mirrors Laravel's `migrations` table layout but lives separately so DB schema
 * migrations and product-catalog schema definitions don't fight for the same history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_schema_migrations', function (Blueprint $table) {
            $table->id();
            $table->string('migration');
            $table->integer('batch');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_schema_migrations');
    }
};
