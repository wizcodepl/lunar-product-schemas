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
];
