<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeCommand extends Command
{
    protected $signature = 'product-schema:make {name : Description of the change, e.g. add_color_to_t_shirts}';

    protected $description = 'Create a new product-catalog schema definition file';

    public function handle(): int
    {
        $rawName = (string) $this->argument('name');
        $name = Str::snake(Str::lower($rawName));
        $name = preg_replace('/[^a-z0-9_]/', '_', $name);

        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_{$name}.php";
        $path = config('lunar-product-schemas.path');

        if (! File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true);
        }

        $target = $path.DIRECTORY_SEPARATOR.$filename;
        $stub = (string) File::get(__DIR__.'/../stubs/product-schema.stub');

        File::put($target, $stub);
        $this->info("Created {$target}");

        return self::SUCCESS;
    }
}
