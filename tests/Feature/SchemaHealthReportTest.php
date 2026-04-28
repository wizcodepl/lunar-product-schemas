<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas\Tests\Feature;

use Lunar\FieldTypes\Text;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use WizcodePl\LunarProductSchemas\ProductSchema;
use WizcodePl\LunarProductSchemas\Reports\SchemaHealthReport;
use WizcodePl\LunarProductSchemas\Tests\TestCase;

class SchemaHealthReportTest extends TestCase
{
    public function test_returns_empty_array_when_no_product_types(): void
    {
        $stats = app(SchemaHealthReport::class)->compute();

        $this->assertSame([], $stats);
    }

    public function test_type_without_required_attributes_marks_every_product_complete(): void
    {
        $this->seedLunarBaseData();

        ProductSchema::productType('t-shirts')
            ->attribute('material')   // searchable=true default, required=false
            ->attribute('description');

        $type = ProductType::where('handle', 't-shirts')->first();
        Product::factory()->create(['product_type_id' => $type->id]);
        Product::factory()->create(['product_type_id' => $type->id]);

        $stats = app(SchemaHealthReport::class)->forType('t-shirts');

        $this->assertSame(2, $stats->totalProducts);
        $this->assertSame(2, $stats->complete);
        $this->assertSame(0, $stats->partial);
        $this->assertSame(0, $stats->missing);
        $this->assertSame([], $stats->requiredAttributeHandles);
    }

    public function test_classifies_complete_partial_missing_correctly(): void
    {
        $this->seedLunarBaseData();

        ProductSchema::productType('t-shirts')
            ->attribute('material', required: true)
            ->attribute('gtin', required: true);

        $type = ProductType::where('handle', 't-shirts')->first();

        // Complete — both required filled.
        Product::factory()->create([
            'product_type_id' => $type->id,
            'attribute_data' => collect([
                'material' => new Text('cotton'),
                'gtin' => new Text('5901234123457'),
            ]),
        ]);

        // Partial — material set, gtin missing.
        Product::factory()->create([
            'product_type_id' => $type->id,
            'attribute_data' => collect([
                'material' => new Text('cotton'),
            ]),
        ]);

        // Missing — both required absent.
        Product::factory()->create([
            'product_type_id' => $type->id,
            'attribute_data' => collect([]),
        ]);

        $stats = app(SchemaHealthReport::class)->forType('t-shirts');

        $this->assertSame(3, $stats->totalProducts);
        $this->assertSame(1, $stats->complete);
        $this->assertSame(1, $stats->partial);
        $this->assertSame(1, $stats->missing);
    }

    public function test_missing_by_attribute_breakdown_is_accurate(): void
    {
        $this->seedLunarBaseData();

        ProductSchema::productType('t-shirts')
            ->attribute('material', required: true)
            ->attribute('gtin', required: true)
            ->attribute('brand', required: true);

        $type = ProductType::where('handle', 't-shirts')->first();

        // 3 products, all with brand+gtin set, none with material.
        for ($i = 0; $i < 3; $i++) {
            Product::factory()->create([
                'product_type_id' => $type->id,
                'attribute_data' => collect([
                    'gtin' => new Text("X{$i}"),
                    'brand' => new Text("Brand{$i}"),
                ]),
            ]);
        }
        // 2 products missing gtin AND brand (so partial — has material).
        for ($i = 0; $i < 2; $i++) {
            Product::factory()->create([
                'product_type_id' => $type->id,
                'attribute_data' => collect(['material' => new Text('cotton')]),
            ]);
        }

        $stats = app(SchemaHealthReport::class)->forType('t-shirts');

        $this->assertSame(3, $stats->missingByAttribute['material']);
        $this->assertSame(2, $stats->missingByAttribute['gtin']);
        $this->assertSame(2, $stats->missingByAttribute['brand']);
    }

    public function test_empty_string_attribute_value_treated_as_missing(): void
    {
        $this->seedLunarBaseData();

        ProductSchema::productType('t-shirts')->attribute('material', required: true);
        $type = ProductType::where('handle', 't-shirts')->first();

        Product::factory()->create([
            'product_type_id' => $type->id,
            'attribute_data' => collect(['material' => new Text('')]),
        ]);

        $stats = app(SchemaHealthReport::class)->forType('t-shirts');

        $this->assertSame(0, $stats->complete);
        $this->assertSame(1, $stats->missing);
    }

    public function test_compute_returns_one_entry_per_product_type(): void
    {
        $this->seedLunarBaseData();

        ProductSchema::productType('t-shirts')->attribute('material', required: true);
        ProductSchema::productType('shoes')->attribute('size', required: true);
        ProductSchema::productType('bags');

        $stats = app(SchemaHealthReport::class)->compute();

        $this->assertCount(3, $stats);
        $handles = collect($stats)->pluck('productType.handle')->all();
        $this->assertContains('t-shirts', $handles);
        $this->assertContains('shoes', $handles);
        $this->assertContains('bags', $handles);
    }

    public function test_for_type_returns_null_for_unknown_handle(): void
    {
        $stats = app(SchemaHealthReport::class)->forType('nonexistent');

        $this->assertNull($stats);
    }

    public function test_products_missing_returns_only_offenders(): void
    {
        $this->seedLunarBaseData();

        ProductSchema::productType('t-shirts')->attribute('material', required: true);
        $type = ProductType::where('handle', 't-shirts')->first();

        $offender = Product::factory()->create([
            'product_type_id' => $type->id,
            'attribute_data' => collect([]),
        ]);
        $compliant = Product::factory()->create([
            'product_type_id' => $type->id,
            'attribute_data' => collect(['material' => new Text('cotton')]),
        ]);

        $missing = app(SchemaHealthReport::class)
            ->productsMissing('t-shirts', 'material');

        $this->assertCount(1, $missing);
        $this->assertSame($offender->id, $missing->first()->id);
    }

    public function test_complete_percentage_handles_zero_products(): void
    {
        $this->seedLunarBaseData();

        ProductSchema::productType('t-shirts')->attribute('material', required: true);

        $stats = app(SchemaHealthReport::class)->forType('t-shirts');

        $this->assertSame(0.0, $stats->completePercentage());
    }

    public function test_complete_percentage_calculates_correctly(): void
    {
        $this->seedLunarBaseData();

        ProductSchema::productType('t-shirts')->attribute('material', required: true);
        $type = ProductType::where('handle', 't-shirts')->first();

        // 4 complete, 1 missing — 80% complete.
        for ($i = 0; $i < 4; $i++) {
            Product::factory()->create([
                'product_type_id' => $type->id,
                'attribute_data' => collect(['material' => new Text("v{$i}")]),
            ]);
        }
        Product::factory()->create([
            'product_type_id' => $type->id,
            'attribute_data' => collect([]),
        ]);

        $stats = app(SchemaHealthReport::class)->forType('t-shirts');

        $this->assertSame(80.0, $stats->completePercentage());
    }
}
