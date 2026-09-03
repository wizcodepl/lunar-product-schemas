<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas\Tests\Feature;

use Lunar\Core\Enums\FieldTypeEnum;
use Lunar\Core\FieldTypes\Text;
use Lunar\Core\FieldTypes\TranslatedText;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductVariant;
use Lunar\Core\Models\TaxClass;
use WizcodePl\LunarProductSchemas\Exceptions\UnknownAttributeException;
use WizcodePl\LunarProductSchemas\Observers\ProductSchemaObserver;
use WizcodePl\LunarProductSchemas\Observers\ProductVariantSchemaObserver;
use WizcodePl\LunarProductSchemas\ProductSchema;
use WizcodePl\LunarProductSchemas\Tests\TestCase;

class StrictModeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['lunar-product-schemas.strict_mode' => true]);

        // Re-register observers — service provider already booted with the default config.
        Product::observe(ProductSchemaObserver::class);
        ProductVariant::observe(ProductVariantSchemaObserver::class);

        $this->seedLunarBaseData();
    }

    public function test_saves_product_when_attribute_data_keys_match_schema(): void
    {
        $type = ProductSchema::productType('shirt')
            ->attribute('subtitle', name: 'Subtitle', type: FieldTypeEnum::TranslatedText, group: 'general', groupName: 'General')
            ->attribute('product_brand', name: 'Brand', group: 'general', groupName: 'General')
            ->model();

        $product = Product::create([
            'product_type_id' => $type->id,
            'status' => 'published',
            'name' => ['en' => 'Tee'],
            'attribute_data' => [
                'subtitle' => new TranslatedText(collect(['en' => 'Soft cotton tee'])),
                'product_brand' => new Text('Acme'),
            ],
        ]);

        $this->assertSame('Soft cotton tee', $product->translateAttribute('subtitle'));
    }

    /**
     * Lunar v2 keys `attribute_data` by attribute id, so the strict check
     * catches attributes that exist but are not mapped to this product type.
     */
    public function test_throws_when_product_attribute_data_has_key_not_mapped_to_type(): void
    {
        $type = ProductSchema::productType('shirt')
            ->attribute('subtitle', name: 'Subtitle', type: FieldTypeEnum::TranslatedText, group: 'general', groupName: 'General')
            ->model();

        ProductSchema::productType('other')->attribute('product_unknown_field');

        $this->expectException(UnknownAttributeException::class);
        $this->expectExceptionMessage('product_unknown_field');

        Product::create([
            'product_type_id' => $type->id,
            'status' => 'published',
            'name' => ['en' => 'Tee'],
            'attribute_data' => [
                'subtitle' => new TranslatedText(collect(['en' => 'Soft cotton tee'])),
                'product_unknown_field' => new Text('boom'),
            ],
        ]);
    }

    public function test_throws_when_variant_attribute_data_has_key_not_mapped_to_type(): void
    {
        $type = ProductSchema::productType('shirt')
            ->attribute('subtitle', name: 'Subtitle', type: FieldTypeEnum::TranslatedText, group: 'general', groupName: 'General')
            ->variantAttribute('variant_size', name: 'Size', group: 'general', groupName: 'General')
            ->model();

        ProductSchema::productType('other')->variantAttribute('variant_phantom');

        $product = Product::create([
            'product_type_id' => $type->id,
            'status' => 'published',
            'name' => ['en' => 'Tee'],
            'attribute_data' => [
                'subtitle' => new TranslatedText(collect(['en' => 'Soft cotton tee'])),
            ],
        ]);

        $this->expectException(UnknownAttributeException::class);
        $this->expectExceptionMessage('variant_phantom');

        $product->variants()->create([
            'sku' => 'TEE-001',
            'tax_class_id' => TaxClass::first()->id,
            'attribute_data' => [
                'variant_size' => new Text('M'),
                'variant_phantom' => new Text('boom'),
            ],
        ]);
    }

    /**
     * A handle that matches no Attribute at all never reaches the observer:
     * Lunar v2's `AsAttributeData` cast discards it on assignment (there is no
     * id to key it by). Documented here so the limitation is explicit.
     */
    public function test_handle_unknown_to_lunar_is_discarded_by_cast_before_strict_check(): void
    {
        $type = ProductSchema::productType('shirt')
            ->attribute('product_brand', name: 'Brand', group: 'general', groupName: 'General')
            ->model();

        $product = Product::create([
            'product_type_id' => $type->id,
            'status' => 'published',
            'name' => ['en' => 'Tee'],
            'attribute_data' => [
                'product_brand' => new Text('Acme'),
                'never_declared_anywhere' => new Text('boom'),
            ],
        ]);

        $this->assertFalse($product->fresh()->attribute_data->has('never_declared_anywhere'));
        $this->assertSame('Acme', $product->fresh()->attribute_data->get('product_brand')->getValue());
    }
}
