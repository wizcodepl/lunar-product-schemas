<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Lunar\FieldTypes\Text;
use Lunar\Models\Attribute;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use WizcodePl\LunarProductSchemas\ProductSchema;
use WizcodePl\LunarProductSchemas\Tests\TestCase;

class ProductSchemaTest extends TestCase
{
    public function test_drop_attribute_removes_attribute_row(): void
    {
        ProductSchema::productType('t-shirts')->attribute('color');

        ProductSchema::dropAttribute('color');

        $this->assertDatabaseMissing('lunar_attributes', ['handle' => 'color']);
    }

    public function test_drop_attribute_wipes_pivot_rows_for_every_type(): void
    {
        ProductSchema::productType('t-shirts')->attribute('color');
        ProductSchema::productType('shoes')->attribute('color');

        $attrId = Attribute::where('handle', 'color')->value('id');
        $this->assertSame(2, DB::table('lunar_attributables')->where('attribute_id', $attrId)->count());

        ProductSchema::dropAttribute('color');

        $this->assertSame(0, DB::table('lunar_attributables')->where('attribute_id', $attrId)->count());
    }

    public function test_drop_attribute_strips_value_from_every_product(): void
    {
        $this->seedLunarBaseData();

        ProductSchema::productType('t-shirts')->attribute('color');
        ProductSchema::productType('shoes')->attribute('color');

        $tshirts = ProductType::where('handle', 't-shirts')->first();
        $shoes = ProductType::where('handle', 'shoes')->first();

        $tshirtsProduct = Product::factory()->create([
            'product_type_id' => $tshirts->id,
            'attribute_data' => collect(['color' => new Text('red'), 'name' => new Text('A')]),
        ]);
        $shoesProduct = Product::factory()->create([
            'product_type_id' => $shoes->id,
            'attribute_data' => collect(['color' => new Text('blue'), 'name' => new Text('B')]),
        ]);

        ProductSchema::dropAttribute('color');

        $this->assertFalse($tshirtsProduct->fresh()->attribute_data->has('color'));
        $this->assertFalse($shoesProduct->fresh()->attribute_data->has('color'));
    }

    public function test_drop_attribute_is_noop_for_missing_handle(): void
    {
        ProductSchema::dropAttribute('does_not_exist');

        $this->assertSame(0, Attribute::count());
    }

    public function test_drop_product_type_deletes_only_the_type(): void
    {
        ProductSchema::productType('legacy-products')->attribute('color');

        ProductSchema::dropProductType('legacy-products');

        $this->assertDatabaseMissing('lunar_product_types', ['handle' => 'legacy-products']);
        // Attribute itself stays — global drop is a separate operation.
        $this->assertDatabaseHas('lunar_attributes', ['handle' => 'color']);
    }
}
