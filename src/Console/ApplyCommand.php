<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas\Console;

use Illuminate\Console\Command;
use WizcodePl\LunarProductSchemas\Migrations\ProductSchemaMigrator;

class ApplyCommand extends Command
{
    protected $signature = 'product-schema:apply {--pretend : Print SQL without executing}';

    protected $description = 'Apply pending product-catalog schema definitions';

    public function handle(ProductSchemaMigrator $migrator): int
    {
        $migrator->setOutput($this->output);

        if (! $migrator->repositoryExists()) {
            $this->error('Tracking table is missing. Run `php artisan migrate` first to create the product_schema_migrations table.');

            return self::FAILURE;
        }

        $migrator->run([config('lunar-product-schemas.path')], [
            'pretend' => (bool) $this->option('pretend'),
        ]);

        return self::SUCCESS;
    }
}
