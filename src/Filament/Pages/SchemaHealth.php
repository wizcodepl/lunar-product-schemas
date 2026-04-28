<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Lunar\Models\Product;
use WizcodePl\LunarProductSchemas\Reports\ProductTypeHealth;
use WizcodePl\LunarProductSchemas\Reports\SchemaHealthReport;

class SchemaHealth extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?string $navigationLabel = 'Schema Health';

    protected static ?string $title = 'Schema Health';

    protected static ?string $slug = 'schema-health';

    protected static string $view = 'lunar-product-schemas::filament.pages.schema-health';

    /** Drill-down state — set when a user clicks a missing-attribute breakdown link. */
    public ?string $drillDownType = null;

    public ?string $drillDownAttribute = null;

    /**
     * @return array<int, ProductTypeHealth>
     */
    public function getStats(): array
    {
        return app(SchemaHealthReport::class)->compute();
    }

    public function showMissing(string $productTypeHandle, string $attributeHandle): void
    {
        $this->drillDownType = $productTypeHandle;
        $this->drillDownAttribute = $attributeHandle;
    }

    public function clearDrillDown(): void
    {
        $this->drillDownType = null;
        $this->drillDownAttribute = null;
    }

    /**
     * @return Collection<int, Product>|null
     */
    public function getDrillDownProducts()
    {
        if ($this->drillDownType === null || $this->drillDownAttribute === null) {
            return null;
        }

        return app(SchemaHealthReport::class)
            ->productsMissing($this->drillDownType, $this->drillDownAttribute);
    }
}
