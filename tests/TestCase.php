<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas\Tests;

use Cartalyst\Converter\Laravel\ConverterServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kalnoy\Nestedset\NestedSetServiceProvider;
use Laravel\Scout\ScoutServiceProvider;
use Lunar\LunarServiceProvider;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\TaxClass;

use function Orchestra\Testbench\default_migration_path;
use function Orchestra\Testbench\load_migration_paths;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\LaravelBlink\BlinkServiceProvider;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use WizcodePl\LunarProductSchemas\ProductSchemaServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        // Filter to providers whose classes are actually loadable. Lunar's
        // optional deps shift between minor versions (e.g. 1.5-beta dropped
        // cartalyst/converter and doctrine/dbal), and Testbench fatally errors
        // if it tries to register a missing class.
        return array_values(array_filter([
            ConverterServiceProvider::class,
            ActivitylogServiceProvider::class,
            BlinkServiceProvider::class,
            MediaLibraryServiceProvider::class,
            ScoutServiceProvider::class,
            NestedSetServiceProvider::class,
            LunarServiceProvider::class,
            ProductSchemaServiceProvider::class,
        ], static fn (string $class): bool => class_exists($class)));
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', $this->testing_connection_config());

        $app['config']->set('app.locale', 'en');
    }

    /**
     * Lunar 1.3 ships migrations with FKs to Laravel's default `users` table
     * (e.g. `lunar_customer_user`). SQLite tolerates dangling references at
     * table-creation time; MySQL doesn't, so the framework migrations must
     * land before Lunar's.
     *
     * Direct load_migration_paths() instead of $this->loadMigrationsFrom():
     * the latter only registers paths on the very first test (when
     * RefreshDatabaseState::$migrated is false). On subsequent tests it falls
     * through to running up() and pushing a MigrateProcessor into
     * cachedTestMigratorProcessors, which then triggers a tearDown rollback
     * that calls Schema::dropIfExists('users') — and MySQL refuses because
     * lunar_customer_user still has an FK pointing at it.
     */
    protected function defineDatabaseMigrations(): void
    {
        load_migration_paths($this->app, default_migration_path());
    }

    /**
     * Build the testing connection from env so CI can swap SQLite ↔ MySQL via DB_* vars.
     * Defaults to in-memory SQLite for local dev (`vendor/bin/phpunit` with no env setup).
     */
    private function testing_connection_config(): array
    {
        $driver = env('DB_CONNECTION', 'sqlite');

        if ($driver === 'sqlite') {
            return [
                'driver' => 'sqlite',
                'database' => env('DB_DATABASE', ':memory:'),
                'prefix' => '',
                'foreign_key_constraints' => true,
            ];
        }

        return [
            'driver' => $driver,
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', $driver === 'pgsql' ? '5432' : '3306'),
            'database' => env('DB_DATABASE', 'lunar_test'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ];
    }

    /**
     * Seed the bare-minimum Lunar reference data needed when a test creates real Products.
     * ProductType / Attribute / AttributeGroup tests do NOT need this — only call it explicitly.
     */
    protected function seedLunarBaseData(): void
    {
        Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'default' => true]);
        Currency::firstOrCreate(['code' => 'USD'], [
            'name' => 'US Dollar',
            'exchange_rate' => 1,
            'decimal_places' => 2,
            'default' => true,
            'enabled' => true,
        ]);
        Channel::firstOrCreate(['handle' => 'webstore'], ['name' => 'Webstore', 'default' => true]);
        TaxClass::firstOrCreate(['name' => 'Default'], ['default' => true]);
    }
}
