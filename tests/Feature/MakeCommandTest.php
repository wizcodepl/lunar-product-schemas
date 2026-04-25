<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas\Tests\Feature;

use Illuminate\Support\Facades\File;
use WizcodePl\LunarProductSchemas\Tests\TestCase;

class MakeCommandTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir().'/lps-make-'.uniqid();
        config()->set('lunar-product-schemas.path', $this->path);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->path)) {
            File::deleteDirectory($this->path);
        }

        parent::tearDown();
    }

    public function test_creates_timestamped_file_from_stub(): void
    {
        $this->artisan('product-schema:make', ['name' => 'add_color_to_t_shirts'])->assertSuccessful();

        $files = File::files($this->path);
        $this->assertCount(1, $files);

        $name = $files[0]->getFilename();
        $this->assertMatchesRegularExpression('/^\d{4}_\d{2}_\d{2}_\d{6}_add_color_to_t_shirts\.php$/', $name);

        $contents = file_get_contents($files[0]->getPathname());
        $this->assertStringContainsString('use WizcodePl\\LunarProductSchemas\\ProductSchema;', $contents);
        $this->assertStringContainsString('return new class extends Migration', $contents);
    }

    public function test_normalizes_messy_input_to_snake_case(): void
    {
        $this->artisan('product-schema:make', ['name' => 'Add Color To T-Shirts!!'])->assertSuccessful();

        $files = File::files($this->path);
        $this->assertMatchesRegularExpression('/_add_color_to_t_shirts_+\.php$/', $files[0]->getFilename());
    }

    public function test_creates_target_directory_when_missing(): void
    {
        $this->assertFalse(is_dir($this->path));

        $this->artisan('product-schema:make', ['name' => 'init'])->assertSuccessful();

        $this->assertTrue(is_dir($this->path));
    }
}
