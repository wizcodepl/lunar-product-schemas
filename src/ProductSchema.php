<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas;

use Illuminate\Support\Facades\DB;
use Lunar\Models\Attribute;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use WizcodePl\LunarProductSchemas\Builders\AttributeBuilder;
use WizcodePl\LunarProductSchemas\Builders\ProductTypeBuilder;
use WizcodePl\LunarProductSchemas\Builders\ProductTypesBuilder;

/**
 * Migration-style schema builder for Lunar product types and attributes.
 *
 * Use inside Laravel migrations:
 *
 *   ProductSchema::productType('t-shirts', 'T-shirts')
 *       ->attribute('size', filterable: true, required: true)
 *       ->attribute('color', filterable: true, searchable: true)
 *       ->dropAttribute('legacy_field');
 *
 *   ProductSchema::attribute('color')->filterable(true);
 *   ProductSchema::dropAttribute('legacy_field');
 */
class ProductSchema
{
    /**
     * Get a builder scoped to one product type. Creates the type if missing.
     */
    public static function productType(string $handle, ?string $name = null): ProductTypeBuilder
    {
        return new ProductTypeBuilder($handle, $name);
    }

    /**
     * Get a builder that fans out the same attribute schema across multiple product types.
     *
     * @param array<string, string|null>|array<int, string> $types
     *                                                             Map of handle => display name, or flat list of handles (names auto-derived).
     */
    public static function productTypes(array $types): ProductTypesBuilder
    {
        return new ProductTypesBuilder($types);
    }

    /**
     * Get a builder for global attribute operations (toggle flags, rename).
     */
    public static function attribute(string $handle): AttributeBuilder
    {
        return new AttributeBuilder($handle);
    }

    /**
     * Drop an attribute from every product type and strip its values from products' attribute_data JSON.
     */
    public static function dropAttribute(string $handle): void
    {
        $attribute = Attribute::query()
            ->where('handle', $handle)
            ->where('attribute_type', Product::morphName())
            ->first();

        if (! $attribute) {
            return;
        }

        self::stripAttributeFromProducts($handle);

        // Lunar uses a polymorphic pivot (lunar_attributables) that lacks cascade.
        // Wipe pivot rows manually before deleting the attribute itself.
        DB::table(config('lunar.database.table_prefix').'attributables')
            ->where('attribute_id', $attribute->id)
            ->delete();

        $attribute->delete();
    }

    /**
     * Drop a product type. Lunar cascades the pivot, but products of this type are NOT deleted.
     */
    public static function dropProductType(string $handle): void
    {
        ProductType::where('handle', $handle)->delete();
    }

    private static function stripAttributeFromProducts(string $handle): void
    {
        Product::query()->chunkById(500, function ($products) use ($handle) {
            foreach ($products as $product) {
                $data = $product->attribute_data;
                if ($data?->has($handle)) {
                    $data->forget($handle);
                    $product->attribute_data = $data;
                    $product->saveQuietly();
                }
            }
        });
    }
}
