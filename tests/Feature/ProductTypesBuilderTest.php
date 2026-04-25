<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas\Tests\Feature;

use Lunar\Models\Attribute;
use Lunar\Models\ProductType;
use WizcodePl\LunarProductSchemas\ProductSchema;
use WizcodePl\LunarProductSchemas\Tests\TestCase;

class ProductTypesBuilderTest extends TestCase
{
    public function test_creates_each_product_type_from_handle_name_map(): void
    {
        ProductSchema::productTypes([
            't-shirts' => 'T-shirts',
            'shoes' => 'Shoes',
        ]);

        $this->assertSame('T-shirts', ProductType::where('handle', 't-shirts')->value('name'));
        $this->assertSame('Shoes', ProductType::where('handle', 'shoes')->value('name'));
    }

    public function test_creates_each_product_type_from_flat_handle_list(): void
    {
        ProductSchema::productTypes(['t-shirts', 'shoes']);

        // Flat-list mode auto-derives names from handles via Str::headline.
        $this->assertSame('T Shirts', ProductType::where('handle', 't-shirts')->value('name'));
        $this->assertSame('Shoes', ProductType::where('handle', 'shoes')->value('name'));
    }

    public function test_attribute_fans_out_to_every_type(): void
    {
        ProductSchema::productTypes(['t-shirts', 'shoes', 'bags'])
            ->attribute('color', filterable: true);

        $attr = Attribute::where('handle', 'color')->first();
        $this->assertNotNull($attr);
        $this->assertTrue((bool) $attr->filterable);

        foreach (['t-shirts', 'shoes', 'bags'] as $handle) {
            $type = ProductType::where('handle', $handle)->first();
            $this->assertTrue(
                $type->mappedAttributes()->where('attribute_id', $attr->id)->exists(),
                "expected '{$handle}' to be attached to color"
            );
        }
    }

    public function test_only_restricts_subsequent_calls_to_subset(): void
    {
        ProductSchema::productTypes(['t-shirts', 'shoes', 'bags'])
            ->attribute('color')                          // all three
            ->only('t-shirts', 'bags')
            ->attribute('pattern');                       // only these two

        $color = Attribute::where('handle', 'color')->first();
        $pattern = Attribute::where('handle', 'pattern')->first();

        $shoes = ProductType::where('handle', 'shoes')->first();
        $this->assertTrue($shoes->mappedAttributes()->where('attribute_id', $color->id)->exists());
        $this->assertFalse($shoes->mappedAttributes()->where('attribute_id', $pattern->id)->exists());

        $tshirts = ProductType::where('handle', 't-shirts')->first();
        $this->assertTrue($tshirts->mappedAttributes()->where('attribute_id', $pattern->id)->exists());
    }

    public function test_only_does_not_mutate_original_builder(): void
    {
        $all = ProductSchema::productTypes(['t-shirts', 'shoes']);
        $all->only('t-shirts');

        // Calling attribute() on the original should still fan out to both.
        $all->attribute('color');

        $color = Attribute::where('handle', 'color')->first();
        foreach (['t-shirts', 'shoes'] as $handle) {
            $this->assertTrue(
                ProductType::where('handle', $handle)->first()
                    ->mappedAttributes()->where('attribute_id', $color->id)->exists()
            );
        }
    }

    public function test_drop_attribute_fans_out(): void
    {
        ProductSchema::productTypes(['t-shirts', 'shoes'])
            ->attribute('color')
            ->dropAttribute('color');

        $color = Attribute::where('handle', 'color')->first();

        foreach (['t-shirts', 'shoes'] as $handle) {
            $type = ProductType::where('handle', $handle)->first();
            $this->assertFalse($type->mappedAttributes()->where('attribute_id', $color->id)->exists());
        }
    }

    public function test_sync_attributes_fans_out(): void
    {
        ProductSchema::productTypes(['t-shirts', 'shoes'])
            ->attribute('color')
            ->attribute('size')
            ->attribute('weight_kg')
            ->syncAttributes(['color']);

        foreach (['t-shirts', 'shoes'] as $handle) {
            $handles = ProductType::where('handle', $handle)->first()
                ->mappedAttributes()->pluck('handle')->all();
            $this->assertSame(['color'], $handles);
        }
    }
}
