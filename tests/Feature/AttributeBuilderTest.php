<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas\Tests\Feature;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Lunar\FieldTypes\Text;
use Lunar\Models\Attribute;
use Lunar\Models\Product;
use WizcodePl\LunarProductSchemas\ProductSchema;
use WizcodePl\LunarProductSchemas\Tests\TestCase;

class AttributeBuilderTest extends TestCase
{
    public function test_filterable_toggle(): void
    {
        ProductSchema::productType('t-shirts')->attribute('color');

        ProductSchema::attribute('color')->filterable(true);
        $this->assertTrue((bool) Attribute::where('handle', 'color')->value('filterable'));

        ProductSchema::attribute('color')->filterable(false);
        $this->assertFalse((bool) Attribute::where('handle', 'color')->value('filterable'));
    }

    public function test_searchable_and_required_toggles(): void
    {
        ProductSchema::productType('t-shirts')->attribute('color');

        ProductSchema::attribute('color')->searchable(false)->required(true);

        $attr = Attribute::where('handle', 'color')->first();
        $this->assertFalse((bool) $attr->searchable);
        $this->assertTrue((bool) $attr->required);
    }

    public function test_name_appends_locale_and_keeps_existing(): void
    {
        ProductSchema::productType('t-shirts')
            ->attribute('color', name: ['en' => 'Color']);

        ProductSchema::attribute('color')->name('Kolor', locale: 'pl');

        $attr = Attribute::where('handle', 'color')->first();
        $this->assertSame('Color', $attr->translate('name', 'en'));
        $this->assertSame('Kolor', $attr->translate('name', 'pl'));
    }

    public function test_rename_updates_handle(): void
    {
        ProductSchema::productType('t-shirts')->attribute('size');

        ProductSchema::attribute('size')->rename('dimensions');

        $this->assertDatabaseMissing('lunar_attributes', ['handle' => 'size']);
        $this->assertDatabaseHas('lunar_attributes', ['handle' => 'dimensions']);
    }

    public function test_rename_is_noop_when_handle_unchanged(): void
    {
        ProductSchema::productType('t-shirts')->attribute('color');
        $beforeUpdatedAt = Attribute::where('handle', 'color')->value('updated_at');

        // sleep a tick so updated_at would change if .rename did write
        usleep(1_100_000);
        ProductSchema::attribute('color')->rename('color');

        $afterUpdatedAt = Attribute::where('handle', 'color')->value('updated_at');
        $this->assertEquals($beforeUpdatedAt, $afterUpdatedAt);
    }

    public function test_rename_migrates_attribute_data_keys_in_products(): void
    {
        $this->seedLunarBaseData();

        $type = ProductSchema::productType('t-shirts')->attribute('size')->model();

        $product = Product::factory()->create([
            'product_type_id' => $type->id,
            'attribute_data' => collect([
                'size' => new Text('M'),
                'name' => new Text('My t-shirt'),
            ]),
        ]);

        ProductSchema::attribute('size')->rename('dimensions');

        $data = $product->fresh()->attribute_data;
        $this->assertFalse($data->has('size'));
        $this->assertTrue($data->has('dimensions'));
        $this->assertSame('M', (string) $data->get('dimensions'));
    }

    public function test_constructor_throws_when_attribute_missing(): void
    {
        $this->expectException(ModelNotFoundException::class);

        ProductSchema::attribute('does_not_exist');
    }
}
