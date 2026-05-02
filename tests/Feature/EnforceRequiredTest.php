<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas\Tests\Feature;

use Lunar\FieldTypes\Text;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use WizcodePl\LunarProductSchemas\Exceptions\MissingRequiredAttributeException;
use WizcodePl\LunarProductSchemas\Observers\ProductSchemaObserver;
use WizcodePl\LunarProductSchemas\Observers\ProductVariantSchemaObserver;
use WizcodePl\LunarProductSchemas\ProductSchema;
use WizcodePl\LunarProductSchemas\Tests\TestCase;

class EnforceRequiredTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['lunar-product-schemas.enforce_required' => true]);
        config(['lunar-product-schemas.strict_mode' => false]);

        // Service provider already booted under the default config — re-register the
        // observers so this test's runtime config flip takes effect.
        Product::observe(ProductSchemaObserver::class);
        ProductVariant::observe(ProductVariantSchemaObserver::class);

        $this->seedLunarBaseData();
    }

    public function test_saves_product_when_all_required_attributes_present(): void
    {
        $type = ProductSchema::productType('shirt')
            ->attribute('name', name: ['en' => 'Name'], type: TranslatedText::class, group: 'general', groupName: ['en' => 'General'], required: true)
            ->attribute('product_brand', name: ['en' => 'Brand'], group: 'general', groupName: ['en' => 'General'], required: true)
            ->model();

        $product = Product::create([
            'product_type_id' => $type->id,
            'status' => 'published',
            'attribute_data' => [
                'name' => new TranslatedText(collect(['en' => 'Tee'])),
                'product_brand' => new Text('Acme'),
            ],
        ]);

        $this->assertSame('Acme', $product->attribute_data->get('product_brand')->getValue());
    }

    public function test_throws_when_required_product_attribute_is_missing(): void
    {
        $type = ProductSchema::productType('shirt')
            ->attribute('name', name: ['en' => 'Name'], type: TranslatedText::class, group: 'general', groupName: ['en' => 'General'], required: true)
            ->attribute('product_brand', name: ['en' => 'Brand'], group: 'general', groupName: ['en' => 'General'], required: true)
            ->model();

        $this->expectException(MissingRequiredAttributeException::class);
        $this->expectExceptionMessage('product_brand');

        Product::create([
            'product_type_id' => $type->id,
            'status' => 'published',
            'attribute_data' => [
                'name' => new TranslatedText(collect(['en' => 'Tee'])),
            ],
        ]);
    }

    public function test_throws_when_required_attribute_is_empty_string(): void
    {
        $type = ProductSchema::productType('shirt')
            ->attribute('product_brand', name: ['en' => 'Brand'], group: 'general', groupName: ['en' => 'General'], required: true)
            ->model();

        $this->expectException(MissingRequiredAttributeException::class);
        $this->expectExceptionMessage('product_brand');

        Product::create([
            'product_type_id' => $type->id,
            'status' => 'published',
            'attribute_data' => [
                'product_brand' => new Text(''),
            ],
        ]);
    }

    public function test_optional_missing_attributes_pass(): void
    {
        $type = ProductSchema::productType('shirt')
            ->attribute('name', name: ['en' => 'Name'], type: TranslatedText::class, group: 'general', groupName: ['en' => 'General'], required: true)
            ->attribute('product_brand', name: ['en' => 'Brand'], group: 'general', groupName: ['en' => 'General'])
            ->model();

        $product = Product::create([
            'product_type_id' => $type->id,
            'status' => 'published',
            'attribute_data' => [
                'name' => new TranslatedText(collect(['en' => 'Tee'])),
            ],
        ]);

        $this->assertNotNull($product->id);
    }

    public function test_throws_when_required_variant_attribute_is_missing(): void
    {
        $type = ProductSchema::productType('shirt')
            ->attribute('name', name: ['en' => 'Name'], type: TranslatedText::class, group: 'general', groupName: ['en' => 'General'], required: true)
            ->variantAttribute('variant_size', name: ['en' => 'Size'], group: 'general', groupName: ['en' => 'General'], required: true)
            ->model();

        $product = Product::create([
            'product_type_id' => $type->id,
            'status' => 'published',
            'attribute_data' => [
                'name' => new TranslatedText(collect(['en' => 'Tee'])),
            ],
        ]);

        $this->expectException(MissingRequiredAttributeException::class);
        $this->expectExceptionMessage('variant_size');

        $product->variants()->create([
            'sku' => 'TEE-001',
            'tax_class_id' => TaxClass::first()->id,
            'attribute_data' => [],
        ]);
    }
}
