<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas\Exceptions;

use RuntimeException;

class MissingRequiredAttributeException extends RuntimeException
{
    /**
     * @param array<int, string> $missing
     */
    public static function forProduct(string $productType, array $missing): self
    {
        return new self(sprintf(
            'Product type [%s] schema requires attribute_data key(s) [%s] but they are missing or empty.',
            $productType,
            implode(', ', $missing),
        ));
    }

    /**
     * @param array<int, string> $missing
     */
    public static function forVariant(string $productType, array $missing): self
    {
        return new self(sprintf(
            'Variant of product type [%s] schema requires attribute_data key(s) [%s] but they are missing or empty.',
            $productType,
            implode(', ', $missing),
        ));
    }
}
