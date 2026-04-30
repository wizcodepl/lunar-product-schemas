<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas\Observers;

use Lunar\Models\Product;
use WizcodePl\LunarProductSchemas\Exceptions\UnknownAttributeException;

class ProductSchemaObserver
{
    public function saving(Product $product): void
    {
        $data = $product->attribute_data;
        if ($data === null || $data->isEmpty()) {
            return;
        }

        $type = $product->productType;
        if (! $type) {
            return;
        }

        $allowed = $type->mappedAttributes()
            ->where('attribute_type', Product::morphName())
            ->pluck('handle')
            ->all();

        $unknown = array_values(array_diff($data->keys()->all(), $allowed));
        if ($unknown !== []) {
            throw UnknownAttributeException::forProduct($type->handle, $unknown);
        }
    }
}
