<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas;

use Illuminate\Support\Facades\DB;
use Lunar\Models\Attribute;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use WizcodePl\LunarProductSchemas\Builders\AttributeBuilder;
use WizcodePl\LunarProductSchemas\Builders\ProductTypeBuilder;
use WizcodePl\LunarProductSchemas\Builders\ProductTypesBuilder;

/**
 * Migration-style schema builder for Lunar product types and attributes.
 *
 * Use inside Laravel migrations:
 *
 *   ProductSchema::productType('t-shirts', 'T-shirts')
 *       ->attribute('material', filterable: true, required: true)   // product-level
 *       ->variantAttribute('lead_time_days')                        // variant-level
 *       ->dropAttribute('legacy_field');
 *
 *   ProductSchema::attribute('material')->filterable(true);
 *   ProductSchema::dropAttribute('legacy_field');                   // detects type and cleans the right JSON layer
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
     * Get a builder for global product-level attribute operations (toggle flags, rename).
     */
    public static function attribute(string $handle): AttributeBuilder
    {
        return new AttributeBuilder($handle, Product::morphName());
    }

    /**
     * Get a builder for global variant-level attribute operations (toggle flags, rename).
     */
    public static function variantAttribute(string $handle): AttributeBuilder
    {
        return new AttributeBuilder($handle, ProductVariant::morphName());
    }

    /**
     * Drop an attribute (product-level or variant-level — auto-detected) from every product type
     * and strip its values from the corresponding `attribute_data` JSON layer.
     */
    public static function dropAttribute(string $handle): void
    {
        $attribute = Attribute::query()
            ->where('handle', $handle)
            ->whereIn('attribute_type', [Product::morphName(), ProductVariant::morphName()])
            ->first();

        if (! $attribute) {
            return;
        }

        if ($attribute->attribute_type === ProductVariant::morphName()) {
            self::stripAttributeFromVariants($handle);
        } else {
            self::stripAttributeFromProducts($handle);
        }

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

    private static function stripAttributeFromVariants(string $handle): void
    {
        ProductVariant::query()->chunkById(500, function ($variants) use ($handle) {
            foreach ($variants as $variant) {
                $data = $variant->attribute_data;
                if ($data?->has($handle)) {
                    $data->forget($handle);
                    $variant->attribute_data = $data;
                    $variant->saveQuietly();
                }
            }
        });
    }
}
