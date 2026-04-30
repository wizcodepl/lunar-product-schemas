<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas\Observers;

use Lunar\Models\ProductVariant;
use WizcodePl\LunarProductSchemas\Exceptions\UnknownAttributeException;

class ProductVariantSchemaObserver
{
    public function saving(ProductVariant $variant): void
    {
        $data = $variant->attribute_data;
        if ($data === null || $data->isEmpty()) {
            return;
        }

        $product = $variant->product;
        $type = $product?->productType;
        if (! $type) {
            return;
        }

        $allowed = $type->mappedAttributes()
            ->where('attribute_type', ProductVariant::morphName())
            ->pluck('handle')
            ->all();

        $unknown = array_values(array_diff($data->keys()->all(), $allowed));
        if ($unknown !== []) {
            throw UnknownAttributeException::forVariant($type->handle, $unknown);
        }
    }
}
