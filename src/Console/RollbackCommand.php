<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas\Console;

use Illuminate\Console\Command;
use WizcodePl\LunarProductSchemas\Migrations\ProductSchemaMigrator;

class RollbackCommand extends Command
{
    protected $signature = 'product-schema:rollback
        {--step=0 : How many batches to roll back (0 = last batch only)}
        {--pretend : Print SQL without executing}';

    protected $description = 'Roll back applied product-catalog schema definitions';

    public function handle(ProductSchemaMigrator $migrator): int
    {
        $migrator->setOutput($this->output);

        if (! $migrator->repositoryExists()) {
            $this->error('Tracking table is missing — nothing to roll back.');

            return self::FAILURE;
        }

        $migrator->rollback([config('lunar-product-schemas.path')], [
            'step' => (int) $this->option('step'),
            'pretend' => (bool) $this->option('pretend'),
        ]);

        return self::SUCCESS;
    }
}
