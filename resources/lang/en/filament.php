<?php

declare(strict_types=1);

return [
    'schema_health' => [
        'navigation_label' => 'Schema Health',
        'title' => 'Schema Health',

        // Stat widget
        'stat_complete' => 'Complete',
        'stat_complete_suffix' => 'of catalog',
        'stat_partial' => 'Partial',
        'stat_partial_suffix' => 'some required fields missing',
        'stat_missing' => 'Missing',
        'stat_missing_suffix' => 'all required fields missing',
        'stat_no_products' => 'no products yet',

        // Table columns
        'col_products' => 'Products',
        'col_completeness' => 'Completeness',

        // Actions
        'action_view' => 'View',
        'action_close' => 'Close',

        // Slide-over content
        'required_fields' => 'Required fields',
        'missing_breakdown' => 'Missing field breakdown',
        'missing_label' => 'missing',
        'all_complete' => '✓ All products have required fields filled',
        'no_data' => 'No data.',
        'unnamed_product' => '(unnamed product)',
    ],
];
