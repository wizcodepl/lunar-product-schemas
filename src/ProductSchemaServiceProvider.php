<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas;

use Illuminate\Support\ServiceProvider;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use WizcodePl\LunarProductSchemas\Console\ApplyCommand;
use WizcodePl\LunarProductSchemas\Console\MakeCommand;
use WizcodePl\LunarProductSchemas\Console\RollbackCommand;
use WizcodePl\LunarProductSchemas\Console\StatusCommand;
use WizcodePl\LunarProductSchemas\Migrations\ProductSchemaMigrationRepository;
use WizcodePl\LunarProductSchemas\Migrations\ProductSchemaMigrator;
use WizcodePl\LunarProductSchemas\Observers\ProductSchemaObserver;
use WizcodePl\LunarProductSchemas\Observers\ProductVariantSchemaObserver;

class ProductSchemaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/lunar-product-schemas.php', 'lunar-product-schemas');

        $this->app->singleton(ProductSchemaMigrationRepository::class, function ($app) {
            return new ProductSchemaMigrationRepository($app['db'], 'product_schema_migrations');
        });

        $this->app->singleton(ProductSchemaMigrator::class, function ($app) {
            return new ProductSchemaMigrator(
                $app->make(ProductSchemaMigrationRepository::class),
                $app['db'],
                $app['files'],
                $app['events'],
            );
        });
    }

    public function boot(): void
    {
        // Tracking-table migration runs as part of `php artisan migrate`.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'lunar-product-schemas');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'lunar-product-schemas');

        $this->publishes([
            __DIR__.'/../config/lunar-product-schemas.php' => config_path('lunar-product-schemas.php'),
        ], 'lunar-product-schemas-config');

        $this->publishes([
            __DIR__.'/../resources/lang' => $this->app->langPath('vendor/lunar-product-schemas'),
        ], 'lunar-product-schemas-translations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ApplyCommand::class,
                RollbackCommand::class,
                StatusCommand::class,
                MakeCommand::class,
            ]);
        }

        // Either policy (unknown-key strictness or required-attribute enforcement)
        // attaches the observers — each observer gates its own checks internally.
        if (config('lunar-product-schemas.strict_mode') || config('lunar-product-schemas.enforce_required')) {
            Product::observe(ProductSchemaObserver::class);
            ProductVariant::observe(ProductVariantSchemaObserver::class);
        }
    }
}
