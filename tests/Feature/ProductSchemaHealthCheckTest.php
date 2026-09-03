<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas\Tests\Feature;

use Lunar\Core\FieldTypes\Text;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductType;
use Spatie\Health\Enums\Status;
use WizcodePl\LunarProductSchemas\Health\ProductSchemaHealthCheck;
use WizcodePl\LunarProductSchemas\ProductSchema;
use WizcodePl\LunarProductSchemas\Tests\TestCase;

class ProductSchemaHealthCheckTest extends TestCase
{
    public function test_ok_when_no_product_types_exist(): void
    {
        $result = (new ProductSchemaHealthCheck)->run();

        $this->assertSame(Status::ok(), $result->status);
        $this->assertSame(0, $result->meta['total_products']);
    }

    public function test_ok_when_all_products_complete(): void
    {
        $this->seedLunarBaseData();

        ProductSchema::productType('t-shirts')->attribute('material', required: true);
        $type = ProductType::where('handle', 't-shirts')->first();

        Product::factory()->create([
            'product_type_id' => $type->id,
            'attribute_data' => collect(['material' => new Text('cotton')]),
        ]);

        $result = (new ProductSchemaHealthCheck)->run();

        $this->assertSame(Status::ok(), $result->status);
        $this->assertSame(1, $result->meta['complete']);
        $this->assertSame(100.0, $result->meta['complete_percentage']);
    }

    public function test_failed_when_completeness_below_threshold(): void
    {
        $this->seedLunarBaseData();

        ProductSchema::productType('t-shirts')->attribute('material', required: true);
        $type = ProductType::where('handle', 't-shirts')->first();

        Product::factory()->create([
            'product_type_id' => $type->id,
            'attribute_data' => collect(['material' => new Text('cotton')]),
        ]);
        Product::factory()->create([
            'product_type_id' => $type->id,
            'attribute_data' => collect([]),
        ]);

        $result = (new ProductSchemaHealthCheck)
            ->minCompletePercentage(95.0)
            ->run();

        $this->assertSame(Status::failed(), $result->status);
        $this->assertSame(1, $result->meta['complete']);
        $this->assertSame(1, $result->meta['missing']);
    }

    public function test_warning_when_between_warning_and_min_thresholds(): void
    {
        $this->seedLunarBaseData();

        ProductSchema::productType('t-shirts')->attribute('material', required: true);
        $type = ProductType::where('handle', 't-shirts')->first();

        // 8 of 10 complete = 80%. Min 95 → fail tier; lower min to 70 so we land in warning band.
        for ($i = 0; $i < 8; $i++) {
            Product::factory()->create([
                'product_type_id' => $type->id,
                'attribute_data' => collect(['material' => new Text("v{$i}")]),
            ]);
        }
        for ($i = 0; $i < 2; $i++) {
            Product::factory()->create([
                'product_type_id' => $type->id,
                'attribute_data' => collect([]),
            ]);
        }

        $result = (new ProductSchemaHealthCheck)
            ->minCompletePercentage(70.0)
            ->warningCompletePercentage(90)
            ->run();

        $this->assertSame(Status::warning(), $result->status);
        $this->assertSame(80.0, $result->meta['complete_percentage']);
    }

    public function test_partial_products_classified_separately_from_missing(): void
    {
        $this->seedLunarBaseData();

        ProductSchema::productType('t-shirts')
            ->attribute('material', required: true)
            ->attribute('gtin', required: true);

        $type = ProductType::where('handle', 't-shirts')->first();

        Product::factory()->create([
            'product_type_id' => $type->id,
            'attribute_data' => collect(['material' => new Text('cotton')]),
        ]);
        Product::factory()->create([
            'product_type_id' => $type->id,
            'attribute_data' => collect([]),
        ]);

        $result = (new ProductSchemaHealthCheck)->run();

        $this->assertSame(1, $result->meta['partial']);
        $this->assertSame(1, $result->meta['missing']);
        $this->assertSame(0, $result->meta['complete']);
    }

    public function test_empty_string_value_treated_as_missing(): void
    {
        $this->seedLunarBaseData();

        ProductSchema::productType('t-shirts')->attribute('material', required: true);
        $type = ProductType::where('handle', 't-shirts')->first();

        Product::factory()->create([
            'product_type_id' => $type->id,
            'attribute_data' => collect(['material' => new Text('')]),
        ]);

        $result = (new ProductSchemaHealthCheck)->run();

        $this->assertSame(0, $result->meta['complete']);
        $this->assertSame(1, $result->meta['missing']);
    }
}
