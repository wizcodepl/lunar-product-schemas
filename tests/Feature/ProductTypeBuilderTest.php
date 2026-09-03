<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas\Tests\Feature;

use Lunar\Core\Enums\FieldTypeEnum;
use Lunar\Core\FieldTypes\ListField;
use Lunar\Core\FieldTypes\Text;
use Lunar\Core\FieldTypes\TranslatedText;
use Lunar\Core\Models\Attribute;
use Lunar\Core\Models\AttributeGroup;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductType;
use Lunar\Core\Models\ProductVariant;
use WizcodePl\LunarProductSchemas\ProductSchema;
use WizcodePl\LunarProductSchemas\Tests\TestCase;

class ProductTypeBuilderTest extends TestCase
{
    public function test_creates_product_type_when_missing(): void
    {
        ProductSchema::productType('t-shirts', 'T-shirts');

        $this->assertDatabaseHas('lunar_product_types', [
            'handle' => 't-shirts',
            'name' => 'T-shirts',
        ]);
    }

    public function test_derives_name_from_handle_when_not_given(): void
    {
        ProductSchema::productType('shoes');

        $this->assertSame('Shoes', ProductType::where('handle', 'shoes')->value('name'));
    }

    public function test_updates_name_on_existing_type_when_changed(): void
    {
        ProductSchema::productType('t-shirts', 'T-shirts');
        ProductSchema::productType('t-shirts', 'T-Shirts (renamed)');

        $this->assertSame('T-Shirts (renamed)', ProductType::where('handle', 't-shirts')->value('name'));
        $this->assertSame(1, ProductType::where('handle', 't-shirts')->count());
    }

    public function test_does_not_overwrite_name_when_omitted(): void
    {
        ProductSchema::productType('t-shirts', 'T-shirts');
        ProductSchema::productType('t-shirts');

        $this->assertSame('T-shirts', ProductType::where('handle', 't-shirts')->value('name'));
    }

    public function test_creates_attribute_with_defaults_on_first_call(): void
    {
        ProductSchema::productType('t-shirts')->attribute('color');

        $attr = Attribute::where('handle', 'color')->whereHas('models', fn ($q) => $q->where('model_type', Product::morphName()))->first();

        $this->assertNotNull($attr);
        $this->assertSame(FieldTypeEnum::Text->value, $attr->type);
        $this->assertTrue((bool) $attr->searchable);
        $this->assertFalse((bool) $attr->filterable);
        $this->assertFalse((bool) $attr->required);
        $this->assertFalse((bool) $attr->system);
    }

    public function test_attaches_attribute_to_product_type(): void
    {
        $type = ProductSchema::productType('t-shirts')->attribute('color')->model();
        $attr = Attribute::where('handle', 'color')->first();

        $this->assertTrue($type->attributeMapping()->where('attribute_id', $attr->id)->exists());
    }

    public function test_attribute_attach_is_idempotent(): void
    {
        ProductSchema::productType('t-shirts')->attribute('color')->attribute('color')->attribute('color');

        $type = ProductType::where('handle', 't-shirts')->first();
        $attr = Attribute::where('handle', 'color')->first();

        $this->assertSame(1, $type->attributeMapping()->where('attribute_id', $attr->id)->count());
    }

    public function test_creates_attribute_group_with_display_name(): void
    {
        ProductSchema::productType('t-shirts')->attribute(
            handle: 'size',
            group: 'specifications',
            groupName: 'Specifications',
        );

        $group = AttributeGroup::where('handle', 'specifications')->first();

        $this->assertNotNull($group);
        $this->assertSame('Specifications', $group->name);
    }

    public function test_group_name_defaults_to_headline_of_handle(): void
    {
        ProductSchema::productType('t-shirts')->attribute('size', group: 'tech_specs');

        $this->assertSame('Tech Specs', AttributeGroup::where('handle', 'tech_specs')->value('name'));
    }

    public function test_string_name_is_stored_as_is(): void
    {
        ProductSchema::productType('t-shirts')->attribute('color', name: 'Kolor');

        $this->assertSame('Kolor', Attribute::where('handle', 'color')->value('name'));
    }

    /**
     * Lunar v2 names are plain strings. The v1 locale-keyed array is still
     * accepted so old definition files keep working: current locale wins.
     */
    public function test_locale_keyed_array_name_picks_current_locale(): void
    {
        $this->app->setLocale('pl');

        ProductSchema::productType('t-shirts')->attribute(
            handle: 'color',
            name: ['en' => 'Color', 'pl' => 'Kolor'],
            groupName: ['en' => 'Specifications', 'pl' => 'Specyfikacja'],
        );

        $this->assertSame('Kolor', Attribute::where('handle', 'color')->value('name'));
        $this->assertSame('Specyfikacja', AttributeGroup::where('handle', 'spec')->value('name'));
    }

    public function test_locale_keyed_array_name_falls_back_to_first_entry(): void
    {
        $this->app->setLocale('de');

        ProductSchema::productType('t-shirts')->attribute('color', name: ['en' => 'Color', 'pl' => 'Kolor']);

        $this->assertSame('Color', Attribute::where('handle', 'color')->value('name'));
    }

    public function test_field_type_accepts_enum_key_and_class(): void
    {
        ProductSchema::productType('t-shirts')
            ->attribute('a', type: FieldTypeEnum::Number)
            ->attribute('b', type: 'toggle')
            ->attribute('c', type: ListField::class)
            ->attribute('d', type: TranslatedText::class);

        $this->assertSame(FieldTypeEnum::Number->value, Attribute::where('handle', 'a')->value('type'));
        $this->assertSame(FieldTypeEnum::Toggle->value, Attribute::where('handle', 'b')->value('type'));
        $this->assertSame(FieldTypeEnum::ListField->value, Attribute::where('handle', 'c')->value('type'));
        $this->assertSame(FieldTypeEnum::TranslatedText->value, Attribute::where('handle', 'd')->value('type'));
    }

    public function test_handles_are_slugged_like_lunar_does(): void
    {
        $type = ProductSchema::productType('t-shirts')
            ->attribute('Lead-Time', group: 'Tech Specs')
            ->model();

        $attr = Attribute::where('handle', 'lead_time')->first();

        $this->assertNotNull($attr);
        $this->assertSame('tech_specs', $attr->group->handle);
        $this->assertTrue($type->attributeMapping()->where('attribute_id', $attr->id)->exists());
    }

    public function test_tristate_flags_leave_existing_values_alone(): void
    {
        ProductSchema::productType('t-shirts')->attribute('color', filterable: true, required: true);

        // Re-define without specifying filterable/required — must preserve previous values.
        ProductSchema::productType('t-shirts')->attribute('color', searchable: false);

        $attr = Attribute::where('handle', 'color')->first();
        $this->assertTrue((bool) $attr->filterable);
        $this->assertTrue((bool) $attr->required);
        $this->assertFalse((bool) $attr->searchable);
    }

    public function test_explicit_false_overrides_existing_true(): void
    {
        ProductSchema::productType('t-shirts')->attribute('color', filterable: true);
        ProductSchema::productType('t-shirts')->attribute('color', filterable: false);

        $this->assertFalse((bool) Attribute::where('handle', 'color')->value('filterable'));
    }

    public function test_drop_attribute_detaches_from_type(): void
    {
        $type = ProductSchema::productType('t-shirts')->attribute('color')->model();
        ProductSchema::productType('t-shirts')->dropAttribute('color');

        $attr = Attribute::where('handle', 'color')->first();
        $this->assertNotNull($attr, 'attribute row itself is not deleted by per-type drop');
        $this->assertFalse($type->attributeMapping()->where('attribute_id', $attr->id)->exists());
    }

    public function test_drop_attribute_strips_value_from_this_types_products_only(): void
    {
        $this->seedLunarBaseData();

        ProductSchema::productType('t-shirts')->attribute('color');
        ProductSchema::productType('shoes')->attribute('color');

        $tshirts = ProductType::where('handle', 't-shirts')->first();
        $shoes = ProductType::where('handle', 'shoes')->first();

        $tshirtsProduct = Product::factory()->create([
            'product_type_id' => $tshirts->id,
            'attribute_data' => collect(['color' => new Text('red')]),
        ]);
        $shoesProduct = Product::factory()->create([
            'product_type_id' => $shoes->id,
            'attribute_data' => collect(['color' => new Text('blue')]),
        ]);

        ProductSchema::productType('t-shirts')->dropAttribute('color');

        $this->assertFalse($tshirtsProduct->fresh()->attribute_data->has('color'));
        $this->assertTrue($shoesProduct->fresh()->attribute_data->has('color'));
    }

    public function test_drop_attribute_is_noop_for_unknown_handle(): void
    {
        $type = ProductSchema::productType('t-shirts')->model();

        ProductSchema::productType('t-shirts')->dropAttribute('nope');

        $this->assertSame(0, $type->attributeMapping()->count());
    }

    public function test_sync_attributes_detaches_unlisted(): void
    {
        ProductSchema::productType('t-shirts')
            ->attribute('color')
            ->attribute('size')
            ->attribute('weight_kg');

        ProductSchema::productType('t-shirts')->syncAttributes(['color', 'size']);

        $type = ProductType::where('handle', 't-shirts')->first();
        $handles = $type->attributeMapping()->pluck('handle')->all();

        $this->assertEqualsCanonicalizing(['color', 'size'], $handles);
    }

    public function test_rename_changes_handle_and_optionally_name(): void
    {
        ProductSchema::productType('legacy-products', 'Old name');
        ProductSchema::productType('legacy-products')->rename('archive-products', 'New name');

        $this->assertDatabaseMissing('lunar_product_types', ['handle' => 'legacy-products']);
        $this->assertDatabaseHas('lunar_product_types', ['handle' => 'archive-products', 'name' => 'New name']);
    }

    public function test_rename_without_name_keeps_existing_name(): void
    {
        ProductSchema::productType('legacy-products', 'Original');
        ProductSchema::productType('legacy-products')->rename('archive-products');

        $this->assertSame('Original', ProductType::where('handle', 'archive-products')->value('name'));
    }

    public function test_attribute_persists_configuration_array(): void
    {
        ProductSchema::productType('t-shirts')->attribute('description', configuration: ['richtext' => true]);

        $config = Attribute::where('handle', 'description')->whereHas('models', fn ($q) => $q->where('model_type', Product::morphName()))->value('configuration');
        $this->assertSame(['richtext' => true], $config?->toArray() ?? $config);
    }

    public function test_variant_attribute_persists_configuration_array(): void
    {
        ProductSchema::productType('t-shirts')->variantAttribute('variant_notes', configuration: ['richtext' => true]);

        $config = Attribute::where('handle', 'variant_notes')->whereHas('models', fn ($q) => $q->where('model_type', ProductVariant::morphName()))->value('configuration');
        $this->assertSame(['richtext' => true], $config?->toArray() ?? $config);
    }

    public function test_attribute_configuration_omitted_keeps_existing(): void
    {
        ProductSchema::productType('t-shirts')->attribute('description', configuration: ['richtext' => true]);
        ProductSchema::productType('t-shirts')->attribute('description', filterable: true);

        $config = Attribute::where('handle', 'description')->whereHas('models', fn ($q) => $q->where('model_type', Product::morphName()))->value('configuration');
        $this->assertSame(['richtext' => true], $config?->toArray() ?? $config);
    }
}
