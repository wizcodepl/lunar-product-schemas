<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use WizcodePl\LunarProductSchemas\Migrations\ProductSchemaMigrator;

class StatusCommand extends Command
{
    protected $signature = 'product-schema:status';

    protected $description = 'Show the status of every product-catalog schema definition';

    public function handle(ProductSchemaMigrator $migrator): int
    {
        if (! $migrator->repositoryExists()) {
            $this->error('Tracking table is missing. Run `php artisan migrate` first.');

            return self::FAILURE;
        }

        $path = config('lunar-product-schemas.path');
        $files = collect(File::isDirectory($path) ? File::files($path) : [])
            ->map(fn ($f) => pathinfo($f->getFilename(), PATHINFO_FILENAME))
            ->sort()
            ->values();

        $applied = DB::table('product_schema_migrations')->get()->keyBy('migration');

        $rows = $files->map(function (string $name) use ($applied) {
            $row = $applied->get($name);

            return [$name, $row ? "Ran [batch {$row->batch}]" : 'Pending'];
        })->all();

        if (empty($rows)) {
            $this->info("No schema files found in {$path}.");

            return self::SUCCESS;
        }

        $this->table(['Definition', 'Status'], $rows);

        return self::SUCCESS;
    }
}
