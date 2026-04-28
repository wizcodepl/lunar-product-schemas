<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas\Reports;

use Illuminate\Database\Eloquent\Collection;
use Lunar\Models\Product;
use Lunar\Models\ProductType;

/**
 * Aggregated schema-completeness stats across every ProductType.
 *
 * Reads only what Lunar already exposes: the `required` flag on `Attribute`
 * and the `attribute_data` JSON on `Product`. No new tables, no new concepts.
 *
 * v1.2.0 implementation walks every product per ProductType in PHP. Acceptable
 * up to ~5k SKU per type. Larger catalogs: see the cached-column plan in the
 * package roadmap.
 */
class SchemaHealthReport
{
    /**
     * @return array<int, ProductTypeHealth>
     */
    public function compute(): array
    {
        return ProductType::query()
            ->with(['mappedAttributes' => function ($q) {
                $q->where('attribute_type', Product::morphName())
                    ->where('required', true);
            }])
            ->orderBy('id')
            ->get()
            ->map(fn (ProductType $type) => $this->statsFor($type))
            ->values()
            ->all();
    }

    public function forType(string $handle): ?ProductTypeHealth
    {
        $type = ProductType::query()
            ->with(['mappedAttributes' => function ($q) {
                $q->where('attribute_type', Product::morphName())
                    ->where('required', true);
            }])
            ->where('handle', $handle)
            ->first();

        return $type === null ? null : $this->statsFor($type);
    }

    /**
     * Find every Product of the given type that's missing the named required
     * attribute. Powers the drill-down list in the UI.
     *
     * @return Collection<int, Product>
     */
    public function productsMissing(string $productTypeHandle, string $attributeHandle)
    {
        return Product::query()
            ->whereHas('productType', fn ($q) => $q->where('handle', $productTypeHandle))
            ->get()
            ->filter(fn (Product $p) => ! $this->hasValue($p, $attributeHandle))
            ->values();
    }

    private function statsFor(ProductType $type): ProductTypeHealth
    {
        $requiredAttrs = $type->mappedAttributes;
        $requiredHandles = $requiredAttrs->pluck('handle')->all();

        $products = Product::query()
            ->where('product_type_id', $type->id)
            ->get();

        $complete = 0;
        $partial = 0;
        $missing = 0;
        $missingByAttribute = array_fill_keys($requiredHandles, 0);

        foreach ($products as $product) {
            if ($requiredAttrs->isEmpty()) {
                $complete++;

                continue;
            }

            $missingForProduct = [];
            foreach ($requiredHandles as $handle) {
                if (! $this->hasValue($product, $handle)) {
                    $missingForProduct[] = $handle;
                    $missingByAttribute[$handle]++;
                }
            }

            if ($missingForProduct === []) {
                $complete++;
            } elseif (count($missingForProduct) === count($requiredHandles)) {
                $missing++;
            } else {
                $partial++;
            }
        }

        // Drop zero entries — UI only shows handles that actually have gaps.
        $missingByAttribute = array_filter($missingByAttribute, static fn (int $count) => $count > 0);

        return new ProductTypeHealth(
            productType: $type,
            requiredAttributeHandles: $requiredHandles,
            totalProducts: $products->count(),
            complete: $complete,
            partial: $partial,
            missing: $missing,
            missingByAttribute: $missingByAttribute,
        );
    }

    private function hasValue(Product $product, string $handle): bool
    {
        $data = $product->attribute_data;

        if ($data === null || ! $data->has($handle)) {
            return false;
        }

        $value = $data->get($handle);

        if (is_object($value) && method_exists($value, 'getValue')) {
            $value = $value->getValue();
        }

        return $value !== null && $value !== '' && $value !== [];
    }
}
