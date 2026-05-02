<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas\Observers;

use Lunar\Models\Product;
use WizcodePl\LunarProductSchemas\Exceptions\MissingRequiredAttributeException;
use WizcodePl\LunarProductSchemas\Exceptions\UnknownAttributeException;

class ProductSchemaObserver
{
    public function saving(Product $product): void
    {
        $type = $product->productType;
        if (! $type) {
            return;
        }

        $attributes = $type->mappedAttributes()
            ->where('attribute_type', Product::morphName())
            ->get(['handle', 'required']);

        $allowed = $attributes->pluck('handle')->all();
        $data = $product->attribute_data;

        if (config('lunar-product-schemas.strict_mode') && $data !== null && ! $data->isEmpty()) {
            $unknown = array_values(array_diff($data->keys()->all(), $allowed));
            if ($unknown !== []) {
                throw UnknownAttributeException::forProduct($type->handle, $unknown);
            }
        }

        if (config('lunar-product-schemas.enforce_required')) {
            $missing = $attributes
                ->where('required', true)
                ->pluck('handle')
                ->filter(fn ($handle) => self::isEmpty($data?->get($handle)))
                ->values()
                ->all();

            if ($missing !== []) {
                throw MissingRequiredAttributeException::forProduct($type->handle, $missing);
            }
        }
    }

    /**
     * Treat any Lunar FieldType whose underlying value is null/'' as missing —
     * users mark a Text/TranslatedText/ListField as required and expect the
     * stored value to be populated, not just the wrapping field type.
     */
    private static function isEmpty(mixed $fieldType): bool
    {
        if ($fieldType === null) {
            return true;
        }

        $value = method_exists($fieldType, 'getValue') ? $fieldType->getValue() : $fieldType;

        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_iterable($value)) {
            return empty($value) || (is_countable($value) && count($value) === 0);
        }

        return false;
    }
}
