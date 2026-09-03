<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas;

use Lunar\Core\Models\Attribute;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductType;
use Lunar\Core\Models\ProductVariant;
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
 *   ProductSchema::dropAttribute('legacy_field');                   // detaches everywhere, deletes the row
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
     * Drop an attribute from every product type and delete the row.
     *
     * Lunar v2 does the cleanup itself: deleting an Attribute cascades the
     * `attribute_models` and `product_type_attribute` pivots, and Lunar's
     * AttributeObserver dispatches `PurgeAttributeData`, which strips the
     * attribute's id key out of every Product / ProductVariant `attribute_data`
     * (on your queue, if one is configured). Because `attribute_data` is
     * id-keyed, the value is invisible to the model cast the moment the row
     * is gone — the purge job only reclaims the JSON bytes.
     */
    public static function dropAttribute(string $handle): void
    {
        $attribute = Attribute::query()
            ->where('handle', ProductTypeBuilder::normalizeHandle($handle))
            ->first();

        $attribute?->delete();
    }

    /**
     * Drop a product type. Lunar cascades the `product_type_attribute` pivot.
     *
     * Products are NOT deleted or reassigned: Lunar v2 refuses to delete a
     * product type that still has products (`ProductTypeActionException`), so
     * migrate them explicitly first.
     */
    public static function dropProductType(string $handle): void
    {
        ProductType::query()->where('handle', $handle)->first()?->delete();
    }
}
