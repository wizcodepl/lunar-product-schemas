<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use WizcodePl\LunarProductSchemas\Tests\TestCase;

class StatusCommandTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir().'/lps-status-'.uniqid();
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

    public function test_reports_pending_for_unapplied_files(): void
    {
        File::put("{$this->path}/2026_05_01_120000_add_color.php", '<?php');

        $this->assertSame(0, Artisan::call('product-schema:status'));

        $output = Artisan::output();
        $this->assertStringContainsString('2026_05_01_120000_add_color', $output);
        $this->assertStringContainsString('Pending', $output);
    }

    public function test_reports_applied_with_batch(): void
    {
        File::put("{$this->path}/2026_05_01_120000_add_color.php", '<?php');
        DB::table('product_schema_migrations')->insert([
            'migration' => '2026_05_01_120000_add_color',
            'batch' => 7,
        ]);

        $this->assertSame(0, Artisan::call('product-schema:status'));
        $this->assertStringContainsString('Ran [batch 7]', Artisan::output());
    }

    public function test_reports_when_no_files_present(): void
    {
        $this->assertSame(0, Artisan::call('product-schema:status'));
        $this->assertStringContainsString('No schema files found', Artisan::output());
    }

    public function test_fails_when_tracking_table_missing(): void
    {
        DB::statement('DROP TABLE product_schema_migrations');

        $this->artisan('product-schema:status')->assertFailed();
    }
}
