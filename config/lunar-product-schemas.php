<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Schema definitions path
    |--------------------------------------------------------------------------
    |
    | Where the package looks for product-catalog definitions. Each file there
    | is a Laravel Migration class executed by `product-schema:apply`.
    |
    */
    'path' => database_path('product-schemas'),

    /*
    |--------------------------------------------------------------------------
    | Strict mode
    |--------------------------------------------------------------------------
    |
    | When enabled, saving a Product / ProductVariant whose `attribute_data` keys
    | are not declared in the product type's schema throws
    | `WizcodePl\LunarProductSchemas\Exceptions\UnknownAttributeException`.
    |
    | Off by default so adopting the package on an existing catalog is safe;
    | flip on once your schemas are the source of truth.
    |
    */
    'strict_mode' => env('LUNAR_PRODUCT_SCHEMAS_STRICT', false),
];
