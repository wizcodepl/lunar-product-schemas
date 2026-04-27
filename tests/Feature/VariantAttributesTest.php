<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas\Tests\Feature;

use Lunar\FieldTypes\Text;
use Lunar\Models\Attribute;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use WizcodePl\LunarProductSchemas\ProductSchema;
use WizcodePl\LunarProductSchemas\Tests\TestCase;

class VariantAttributesTest extends TestCase
{
    public function test_variant_attribute_creates_attribute_with_variant_type(): void
    {
        ProductSchema::productType('t-shirts')->variantAttribute('lead_time_days');

        $attr = Attribute::where('handle', 'lead_time_days')->first();

        $this->assertNotNull($attr);
        $this->assertSame(ProductVariant::morphName(), $attr->attribute_type);
    }

    public function test_variant_attribute_creates_with_sane_defaults(): void
    {
        ProductSchema::productType('t-shirts')->variantAttribute('lead_time_days');

        $attr = Attribute::where('handle', 'lead_time_days')->first();

        $this->assertSame(Text::class, $attr->type);
        $this->assertTrue((bool) $attr->searchable);
        $this->assertFalse((bool) $attr->filterable);
        $this->assertFalse((bool) $attr->required);
        $this->assertFalse((bool) $attr->system);
    }

    public function test_variant_attribute_explicit_flags_persist(): void
    {
        ProductSchema::productType('t-shirts')->variantAttribute(
            'lead_time_days',
            filterable: true,
            searchable: false,
            required: true,
        );

        $attr = Attribute::where('handle', 'lead_time_days')
            ->where('attribute_type', ProductVariant::morphName())
            ->first();

        $this->assertTrue((bool) $attr->filterable);
        $this->assertFalse((bool) $attr->searchable);
        $this->assertTrue((bool) $attr->required);
    }

    public function test_variant_attribute_tristate_leaves_existing_flags_alone(): void
    {
        ProductSchema::productType('t-shirts')->variantAttribute(
            'lead_time_days',
            filterable: true,
            required: true,
        );

        // Re-define with only `searchable` specified — filterable/required must keep prior values.
        ProductSchema::productType('t-shirts')->variantAttribute(
            'lead_time_days',
            searchable: false,
        );

        $attr = Attribute::where('handle', 'lead_time_days')
            ->where('attribute_type', ProductVariant::morphName())
            ->first();

        $this->assertTrue((bool) $attr->filterable, 'filterable should be preserved');
        $this->assertTrue((bool) $attr->required, 'required should be preserved');
        $this->assertFalse((bool) $attr->searchable, 'searchable was explicitly set to false');
    }

    public function test_variant_attribute_explicit_false_overrides_existing_true(): void
    {
        ProductSchema::productType('t-shirts')->variantAttribute('lead_time_days', filterable: true);
        ProductSchema::productType('t-shirts')->variantAttribute('lead_time_days', filterable: false);

        $attr = Attribute::where('handle', 'lead_time_days')
            ->where('attribute_type', ProductVariant::morphName())
            ->first();

        $this->assertFalse((bool) $attr->filterable);
    }

    public function test_variant_attribute_builder_toggles_flags(): void
    {
        ProductSchema::productType('t-shirts')->variantAttribute('lead_time_days');

        ProductSchema::variantAttribute('lead_time_days')
            ->filterable(true)
            ->searchable(false)
            ->required(true);

        $attr = Attribute::where('handle', 'lead_time_days')
            ->where('attribute_type', ProductVariant::morphName())
            ->first();

        $this->assertTrue((bool) $attr->filterable);
        $this->assertFalse((bool) $attr->searchable);
        $this->assertTrue((bool) $attr->required);
    }

    public function test_variant_attribute_builder_does_not_collide_with_product_typed_attribute(): void
    {
        // Same handle on both layers — flags must be independent.
        ProductSchema::productType('t-shirts')
            ->attribute('notes', filterable: true)
            ->variantAttribute('notes', filterable: false);

        ProductSchema::variantAttribute('notes')->required(true);

        $productAttr = Attribute::where('handle', 'notes')
            ->where('attribute_type', Product::morphName())
            ->first();
        $variantAttr = Attribute::where('handle', 'notes')
            ->where('attribute_type', ProductVariant::morphName())
            ->first();

        $this->assertTrue((bool) $productAttr->filterable, 'product layer flag untouched');
        $this->assertFalse((bool) $productAttr->required, 'product layer required not flipped');
        $this->assertTrue((bool) $variantAttr->required, 'variant layer flipped');
        $this->assertFalse((bool) $variantAttr->filterable, 'variant layer kept its own filterable');
    }

    public function test_variant_attribute_appears_under_variant_attributes_relation_only(): void
    {
        $type = ProductSchema::productType('t-shirts')
            ->attribute('material')                // product-level
            ->variantAttribute('lead_time_days')   // variant-level
            ->model();

        $variantHandles = $type->variantAttributes()->pluck('handle')->all();
        $productHandles = $type->productAttributes()->pluck('handle')->all();

        $this->assertSame(['lead_time_days'], $variantHandles);
        $this->assertSame(['material'], $productHandles);
    }

    public function test_variant_attribute_attach_is_idempotent(): void
    {
        ProductSchema::productType('t-shirts')
            ->variantAttribute('lead_time_days')
            ->variantAttribute('lead_time_days')
            ->variantAttribute('lead_time_days');

        $type = ProductType::where('handle', 't-shirts')->first();
        $attr = Attribute::where('handle', 'lead_time_days')->first();

        $this->assertSame(1, $type->mappedAttributes()->where('attribute_id', $attr->id)->count());
    }

    public function test_drop_variant_attribute_detaches_from_type(): void
    {
        $type = ProductSchema::productType('t-shirts')
            ->variantAttribute('lead_time_days')
            ->model();

        ProductSchema::productType('t-shirts')->dropVariantAttribute('lead_time_days');

        $attr = Attribute::where('handle', 'lead_time_days')->first();
        $this->assertNotNull($attr, 'attribute row itself stays — per-type drop only detaches');
        $this->assertFalse($type->mappedAttributes()->where('attribute_id', $attr->id)->exists());
    }

    public function test_drop_variant_attribute_strips_value_from_this_types_variants_only(): void
    {
        $this->seedLunarBaseData();

        ProductSchema::productType('t-shirts')->variantAttribute('lead_time_days');
        ProductSchema::productType('shoes')->variantAttribute('lead_time_days');

        $tshirts = ProductType::where('handle', 't-shirts')->first();
        $shoes = ProductType::where('handle', 'shoes')->first();

        $tshirtProduct = Product::factory()->create(['product_type_id' => $tshirts->id]);
        $shoeProduct = Product::factory()->create(['product_type_id' => $shoes->id]);

        $tshirtVariant = ProductVariant::factory()->create([
            'product_id' => $tshirtProduct->id,
            'attribute_data' => collect(['lead_time_days' => new Text('14')]),
        ]);
        $shoeVariant = ProductVariant::factory()->create([
            'product_id' => $shoeProduct->id,
            'attribute_data' => collect(['lead_time_days' => new Text('3')]),
        ]);

        ProductSchema::productType('t-shirts')->dropVariantAttribute('lead_time_days');

        $this->assertFalse($tshirtVariant->fresh()->attribute_data->has('lead_time_days'));
        $this->assertTrue($shoeVariant->fresh()->attribute_data->has('lead_time_days'));
    }

    public function test_drop_variant_attribute_leaves_product_level_data_untouched(): void
    {
        $this->seedLunarBaseData();

        $type = ProductSchema::productType('t-shirts')
            ->attribute('material')
            ->variantAttribute('lead_time_days')
            ->model();

        $product = Product::factory()->create([
            'product_type_id' => $type->id,
            'attribute_data' => collect(['material' => new Text('cotton')]),
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'attribute_data' => collect(['lead_time_days' => new Text('14')]),
        ]);

        ProductSchema::productType('t-shirts')->dropVariantAttribute('lead_time_days');

        // Variant's lead_time_days gone, but product's material still there.
        $this->assertFalse($variant->fresh()->attribute_data->has('lead_time_days'));
        $this->assertTrue($product->fresh()->attribute_data->has('material'));
        $this->assertSame('cotton', (string) $product->fresh()->attribute_data->get('material'));
    }

    public function test_drop_variant_attribute_is_noop_for_unknown_handle(): void
    {
        $type = ProductSchema::productType('t-shirts')
            ->variantAttribute('lead_time_days')
            ->model();

        ProductSchema::productType('t-shirts')->dropVariantAttribute('does_not_exist');

        // Unrelated attr untouched.
        $attr = Attribute::where('handle', 'lead_time_days')->first();
        $this->assertTrue($type->mappedAttributes()->where('attribute_id', $attr->id)->exists());
    }

    public function test_sync_variant_attributes_detaches_unlisted_variant_attrs(): void
    {
        ProductSchema::productType('t-shirts')
            ->variantAttribute('lead_time_days')
            ->variantAttribute('batch_number')
            ->variantAttribute('pantone_code');

        ProductSchema::productType('t-shirts')->syncVariantAttributes(['lead_time_days', 'batch_number']);

        $type = ProductType::where('handle', 't-shirts')->first();
        $variantHandles = $type->variantAttributes()->pluck('handle')->all();

        $this->assertEqualsCanonicalizing(['lead_time_days', 'batch_number'], $variantHandles);
    }

    public function test_sync_variant_attributes_does_not_touch_product_attributes(): void
    {
        ProductSchema::productType('t-shirts')
            ->attribute('material')                // product-level, must stay
            ->variantAttribute('lead_time_days')
            ->variantAttribute('batch_number');

        // Sync drops 'batch_number' from variants. Product-level 'material' must remain attached.
        ProductSchema::productType('t-shirts')->syncVariantAttributes(['lead_time_days']);

        $type = ProductType::where('handle', 't-shirts')->first();
        $productHandles = $type->productAttributes()->pluck('handle')->all();
        $variantHandles = $type->variantAttributes()->pluck('handle')->all();

        $this->assertSame(['material'], $productHandles);
        $this->assertSame(['lead_time_days'], $variantHandles);
    }

    public function test_global_drop_attribute_detects_variant_type_and_strips_variant_json(): void
    {
        $this->seedLunarBaseData();

        $type = ProductSchema::productType('t-shirts')
            ->variantAttribute('lead_time_days')
            ->model();

        $product = Product::factory()->create(['product_type_id' => $type->id]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'attribute_data' => collect(['lead_time_days' => new Text('14')]),
        ]);

        ProductSchema::dropAttribute('lead_time_days');

        $this->assertDatabaseMissing('lunar_attributes', ['handle' => 'lead_time_days']);
        $this->assertFalse($variant->fresh()->attribute_data->has('lead_time_days'));
    }

    public function test_global_drop_attribute_does_not_strip_product_level_value_on_variant_drop(): void
    {
        $this->seedLunarBaseData();

        // Same handle on both layers (Lunar allows it — different attribute_type).
        $type = ProductSchema::productType('t-shirts')
            ->attribute('notes')             // product-level
            ->variantAttribute('notes')      // variant-level (same handle, different layer)
            ->model();

        $product = Product::factory()->create([
            'product_type_id' => $type->id,
            'attribute_data' => collect(['notes' => new Text('product-level note')]),
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'attribute_data' => collect(['notes' => new Text('variant-level note')]),
        ]);

        // dropAttribute() picks the first match — alphabetically 'product' comes first, so this drops the product-level one.
        ProductSchema::dropAttribute('notes');

        $this->assertFalse($product->fresh()->attribute_data->has('notes'), 'product layer cleared');
        $this->assertTrue($variant->fresh()->attribute_data->has('notes'), 'variant layer untouched');
    }

    public function test_variant_attribute_builder_renames_keys_in_variants(): void
    {
        $this->seedLunarBaseData();

        $type = ProductSchema::productType('t-shirts')
            ->variantAttribute('lead_time_days')
            ->model();

        $product = Product::factory()->create(['product_type_id' => $type->id]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'attribute_data' => collect(['lead_time_days' => new Text('14')]),
        ]);

        ProductSchema::variantAttribute('lead_time_days')->rename('processing_days');

        $data = $variant->fresh()->attribute_data;

        $this->assertFalse($data->has('lead_time_days'));
        $this->assertTrue($data->has('processing_days'));
        $this->assertSame('14', (string) $data->get('processing_days'));
        $this->assertDatabaseHas('lunar_attributes', [
            'handle' => 'processing_days',
            'attribute_type' => ProductVariant::morphName(),
        ]);
    }

    public function test_product_types_builder_fanout_variant_attribute(): void
    {
        ProductSchema::productTypes(['t-shirts', 'shoes', 'bags'])
            ->variantAttribute('lead_time_days');

        $attr = Attribute::where('handle', 'lead_time_days')->first();
        $this->assertNotNull($attr);
        $this->assertSame(ProductVariant::morphName(), $attr->attribute_type);

        foreach (['t-shirts', 'shoes', 'bags'] as $handle) {
            $type = ProductType::where('handle', $handle)->first();
            $this->assertTrue(
                $type->mappedAttributes()->where('attribute_id', $attr->id)->exists(),
                "expected variant attr to be attached to {$handle}"
            );
        }
    }

    public function test_product_types_builder_fanout_drop_variant_attribute(): void
    {
        ProductSchema::productTypes(['t-shirts', 'shoes'])
            ->variantAttribute('lead_time_days')
            ->dropVariantAttribute('lead_time_days');

        $attr = Attribute::where('handle', 'lead_time_days')->first();

        foreach (['t-shirts', 'shoes'] as $handle) {
            $type = ProductType::where('handle', $handle)->first();
            $this->assertFalse($type->mappedAttributes()->where('attribute_id', $attr->id)->exists());
        }
    }
}
