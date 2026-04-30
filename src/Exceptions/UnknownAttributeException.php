<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas\Exceptions;

use RuntimeException;

class UnknownAttributeException extends RuntimeException
{
    /**
     * @param  array<int, string>  $unknown
     */
    public static function forProduct(string $productType, array $unknown): self
    {
        return new self(sprintf(
            "Product type [%s] schema does not declare attribute_data key(s) [%s]. Add them to a schema migration before saving.",
            $productType,
            implode(', ', $unknown),
        ));
    }

    /**
     * @param  array<int, string>  $unknown
     */
    public static function forVariant(string $productType, array $unknown): self
    {
        return new self(sprintf(
            "Variant of product type [%s] schema does not declare attribute_data key(s) [%s]. Add them to a schema migration before saving.",
            $productType,
            implode(', ', $unknown),
        ));
    }
}
