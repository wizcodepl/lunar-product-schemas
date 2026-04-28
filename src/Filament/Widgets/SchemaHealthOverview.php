<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use WizcodePl\LunarProductSchemas\Reports\SchemaHealthReport;

/**
 * Three stat cards (Complete / Partial / Missing) summarising catalog health across all
 * ProductTypes. Renders above the SchemaHealth page using Filament's native widget look —
 * matches the styling of every other stat row in Lunar admin.
 */
class SchemaHealthOverview extends StatsOverviewWidget
{
    /** Render full-width above the page content (Filament default for header widgets). */
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $stats = app(SchemaHealthReport::class)->compute();

        $total = array_sum(array_column($stats, 'totalProducts'));
        $complete = array_sum(array_column($stats, 'complete'));
        $partial = array_sum(array_column($stats, 'partial'));
        $missing = array_sum(array_column($stats, 'missing'));

        $percentage = $total > 0 ? ($complete / $total) * 100 : 0.0;

        return [
            Stat::make(__('lunar-product-schemas::filament.schema_health.stat_complete'), $complete)
                ->description($total > 0
                    ? number_format($percentage, 0).'% '.__('lunar-product-schemas::filament.schema_health.stat_complete_suffix')
                    : __('lunar-product-schemas::filament.schema_health.stat_no_products'))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make(__('lunar-product-schemas::filament.schema_health.stat_partial'), $partial)
                ->description(__('lunar-product-schemas::filament.schema_health.stat_partial_suffix'))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('warning'),

            Stat::make(__('lunar-product-schemas::filament.schema_health.stat_missing'), $missing)
                ->description(__('lunar-product-schemas::filament.schema_health.stat_missing_suffix'))
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),
        ];
    }
}
