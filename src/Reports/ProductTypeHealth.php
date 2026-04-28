<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas\Reports;

use Lunar\Models\ProductType;

/**
 * Aggregated data-completeness stats for a single ProductType.
 *
 * `complete` = product has every required attribute set to a non-empty value.
 * `partial`  = at least one required attribute filled, at least one missing.
 * `missing`  = none of the required attributes are filled (or the type has
 *              required attributes but the product carries no attribute_data).
 *
 * `missingByAttribute` maps required attribute handle → number of products
 * lacking a value for it. Lets the UI surface "which field is the bottleneck".
 *
 * When a ProductType has zero attributes flagged `required`, every product
 * counts as `complete` and `missingByAttribute` is empty.
 */
final class ProductTypeHealth
{
    /**
     * @param list<string> $requiredAttributeHandles
     * @param array<string, int> $missingByAttribute
     */
    public function __construct(
        public readonly ProductType $productType,
        public readonly array $requiredAttributeHandles,
        public readonly int $totalProducts,
        public readonly int $complete,
        public readonly int $partial,
        public readonly int $missing,
        public readonly array $missingByAttribute,
    ) {}

    public function completePercentage(): float
    {
        return $this->totalProducts === 0 ? 0.0 : ($this->complete / $this->totalProducts) * 100;
    }
}
