<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Lunar\Models\ProductType;
use WizcodePl\LunarProductSchemas\Filament\Widgets\SchemaHealthOverview;
use WizcodePl\LunarProductSchemas\Reports\SchemaHealthReport;

class SchemaHealth extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $slug = 'schema-health';

    protected static string $view = 'lunar-product-schemas::filament.pages.schema-health';

    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.catalog');
    }

    public static function getNavigationParentItem(): ?string
    {
        return __('lunarpanel::product.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('lunar-product-schemas::filament.schema_health.navigation_label');
    }

    public function getTitle(): string
    {
        return __('lunar-product-schemas::filament.schema_health.title');
    }

    protected function getHeaderWidgets(): array
    {
        return [SchemaHealthOverview::class];
    }

    /**
     * Filament Table — uses ProductType as the data source and augments each row with health
     * stats computed once per request via SchemaHealthReport::compute(). Rows are clickable
     * (recordAction = 'view') and open a slide-over with the per-type breakdown + drill-downs.
     */
    public function table(Table $table): Table
    {
        $stats = collect(app(SchemaHealthReport::class)->compute())
            ->keyBy(fn ($row) => $row->productType->handle);

        return $table
            ->query(ProductType::query())
            ->columns([
                TextColumn::make('name')
                    ->label(__('lunarpanel::producttype.table.name.label'))
                    ->description(fn ($record) => $record->handle)
                    ->searchable(['name', 'handle'])
                    ->sortable(),

                TextColumn::make('total_products')
                    ->label(__('lunar-product-schemas::filament.schema_health.col_products'))
                    ->state(fn ($record) => $stats->get($record->handle)?->totalProducts ?? 0)
                    ->numeric()
                    ->alignEnd(),

                TextColumn::make('completeness')
                    ->label(__('lunar-product-schemas::filament.schema_health.col_completeness'))
                    ->state(fn ($record) => $stats->get($record->handle)?->completePercentage() ?? 0)
                    ->formatStateUsing(fn ($state) => number_format($state, 0).'%')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state >= 95 => 'success',
                        $state >= 70 => 'warning',
                        default => 'danger',
                    }),

                TextColumn::make('complete_count')
                    ->label(__('lunar-product-schemas::filament.schema_health.stat_complete'))
                    ->state(fn ($record) => $stats->get($record->handle)?->complete ?? 0)
                    ->badge()
                    ->color('success')
                    ->numeric()
                    ->alignCenter(),

                TextColumn::make('partial_count')
                    ->label(__('lunar-product-schemas::filament.schema_health.stat_partial'))
                    ->state(fn ($record) => $stats->get($record->handle)?->partial ?? 0)
                    ->badge()
                    ->color('warning')
                    ->numeric()
                    ->alignCenter(),

                TextColumn::make('missing_count')
                    ->label(__('lunar-product-schemas::filament.schema_health.stat_missing'))
                    ->state(fn ($record) => $stats->get($record->handle)?->missing ?? 0)
                    ->badge()
                    ->color('danger')
                    ->numeric()
                    ->alignCenter(),
            ])
            ->actions([
                ViewAction::make()
                    ->label(__('lunar-product-schemas::filament.schema_health.action_view'))
                    ->icon('heroicon-m-eye')
                    ->slideOver()
                    ->modalHeading(fn ($record) => $record->name)
                    ->modalDescription(fn ($record) => $record->handle)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('lunar-product-schemas::filament.schema_health.action_close'))
                    ->modalContent(fn ($record) => view(
                        'lunar-product-schemas::filament.pages.partials.schema-health-detail',
                        ['health' => $stats->get($record->handle)],
                    )),
            ])
            ->recordAction(ViewAction::class)
            ->defaultSort('name')
            ->paginated([10, 25, 50, 'all']);
    }
}
