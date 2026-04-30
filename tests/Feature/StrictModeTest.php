<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas\Tests\Feature;

use Lunar\FieldTypes\Text;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
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
            ->attribute('name', name: ['en' => 'Name'], type: TranslatedText::class, group: 'general', groupName: ['en' => 'General'])
            ->attribute('product_brand', name: ['en' => 'Brand'], group: 'general', groupName: ['en' => 'General'])
            ->model();

        $product = Product::create([
            'product_type_id' => $type->id,
            'status' => 'published',
            'attribute_data' => [
                'name' => new TranslatedText(collect(['en' => 'Tee'])),
                'product_brand' => new Text('Acme'),
            ],
        ]);

        $this->assertSame('Tee', $product->translateAttribute('name'));
    }

    public function test_throws_when_product_attribute_data_has_unknown_key(): void
    {
        $type = ProductSchema::productType('shirt')
            ->attribute('name', name: ['en' => 'Name'], type: TranslatedText::class, group: 'general', groupName: ['en' => 'General'])
            ->model();

        $this->expectException(UnknownAttributeException::class);
        $this->expectExceptionMessage('product_unknown_field');

        Product::create([
            'product_type_id' => $type->id,
            'status' => 'published',
            'attribute_data' => [
                'name' => new TranslatedText(collect(['en' => 'Tee'])),
                'product_unknown_field' => new Text('boom'),
            ],
        ]);
    }

    public function test_throws_when_variant_attribute_data_has_unknown_key(): void
    {
        $type = ProductSchema::productType('shirt')
            ->attribute('name', name: ['en' => 'Name'], type: TranslatedText::class, group: 'general', groupName: ['en' => 'General'])
            ->variantAttribute('variant_size', name: ['en' => 'Size'], group: 'general', groupName: ['en' => 'General'])
            ->model();

        $product = Product::create([
            'product_type_id' => $type->id,
            'status' => 'published',
            'attribute_data' => [
                'name' => new TranslatedText(collect(['en' => 'Tee'])),
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
}
