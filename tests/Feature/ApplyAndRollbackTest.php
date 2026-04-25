<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Lunar\Models\ProductType;
use WizcodePl\LunarProductSchemas\Tests\TestCase;

class ApplyAndRollbackTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir().'/lps-apply-'.uniqid();
        File::makeDirectory($this->path, 0755, true);
        config()->set('lunar-product-schemas.path', $this->path);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->path)) {
            File::deleteDirectory($this->path);
        }

        parent::tearDown();
    }

    public function test_apply_runs_pending_definitions_and_records_them(): void
    {
        $this->writeDefinition('2026_05_01_120000_add_color', <<<'PHP'
ProductSchema::productType('t-shirts', 'T-shirts')
    ->attribute('color', filterable: true);
PHP, <<<'PHP'
ProductSchema::productType('t-shirts')->dropAttribute('color');
PHP);

        $this->artisan('product-schema:apply')->assertSuccessful();

        $this->assertDatabaseHas('lunar_product_types', ['handle' => 't-shirts']);
        $this->assertDatabaseHas('lunar_attributes', ['handle' => 'color', 'filterable' => true]);

        $this->assertDatabaseHas('product_schema_migrations', [
            'migration' => '2026_05_01_120000_add_color',
            'batch' => 1,
        ]);
    }

    public function test_apply_assigns_increasing_batch_numbers_per_run(): void
    {
        $this->writeDefinition('2026_05_01_120000_first', "ProductSchema::productType('a');", "ProductSchema::dropProductType('a');");
        $this->artisan('product-schema:apply')->assertSuccessful();

        $this->writeDefinition('2026_05_02_120000_second', "ProductSchema::productType('b');", "ProductSchema::dropProductType('b');");
        $this->artisan('product-schema:apply')->assertSuccessful();

        $this->assertSame(1, (int) DB::table('product_schema_migrations')
            ->where('migration', '2026_05_01_120000_first')->value('batch'));
        $this->assertSame(2, (int) DB::table('product_schema_migrations')
            ->where('migration', '2026_05_02_120000_second')->value('batch'));
    }

    public function test_apply_skips_already_run_definitions(): void
    {
        $this->writeDefinition('2026_05_01_120000_once', "ProductSchema::productType('a');", "ProductSchema::dropProductType('a');");

        $this->artisan('product-schema:apply')->assertSuccessful();
        $this->artisan('product-schema:apply')->assertSuccessful();

        $this->assertSame(1, DB::table('product_schema_migrations')->count());
    }

    public function test_rollback_undoes_last_batch_only(): void
    {
        $this->writeDefinition(
            '2026_05_01_120000_first',
            "ProductSchema::productType('a', 'Type A');",
            "ProductSchema::dropProductType('a');",
        );
        $this->artisan('product-schema:apply')->assertSuccessful();

        $this->writeDefinition(
            '2026_05_02_120000_second',
            "ProductSchema::productType('b', 'Type B');",
            "ProductSchema::dropProductType('b');",
        );
        $this->artisan('product-schema:apply')->assertSuccessful();

        $this->artisan('product-schema:rollback')->assertSuccessful();

        $this->assertNull(ProductType::where('handle', 'b')->first(), 'second definition should have rolled back');
        $this->assertNotNull(ProductType::where('handle', 'a')->first(), 'first definition stays applied');
        $this->assertDatabaseMissing('product_schema_migrations', ['migration' => '2026_05_02_120000_second']);
        $this->assertDatabaseHas('product_schema_migrations', ['migration' => '2026_05_01_120000_first']);
    }

    public function test_rollback_with_step_undoes_multiple_batches(): void
    {
        $this->writeDefinition('2026_05_01_120000_first', "ProductSchema::productType('a');", "ProductSchema::dropProductType('a');");
        $this->artisan('product-schema:apply')->assertSuccessful();

        $this->writeDefinition('2026_05_02_120000_second', "ProductSchema::productType('b');", "ProductSchema::dropProductType('b');");
        $this->artisan('product-schema:apply')->assertSuccessful();

        $this->artisan('product-schema:rollback', ['--step' => 2])->assertSuccessful();

        $this->assertSame(0, DB::table('product_schema_migrations')->count());
        $this->assertNull(ProductType::where('handle', 'a')->first());
        $this->assertNull(ProductType::where('handle', 'b')->first());
    }

    public function test_apply_fails_when_tracking_table_missing(): void
    {
        DB::statement('DROP TABLE product_schema_migrations');

        $this->artisan('product-schema:apply')->assertFailed();
    }

    private function writeDefinition(string $name, string $upBody, string $downBody): void
    {
        $code = <<<PHP
        <?php

        use Illuminate\Database\Migrations\Migration;
        use WizcodePl\LunarProductSchemas\ProductSchema;

        return new class extends Migration {
            public function up(): void
            {
                {$upBody}
            }

            public function down(): void
            {
                {$downBody}
            }
        };
        PHP;

        file_put_contents("{$this->path}/{$name}.php", $code);
    }
}
