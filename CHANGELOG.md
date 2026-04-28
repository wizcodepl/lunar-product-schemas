# Changelog

All notable changes to `wizcodepl/lunar-product-schemas` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.2.1] - 2026-04-28

### Changed
- **Schema Health page rewritten to be 100% Filament-native.** Header is now a `StatsOverviewWidget` (Complete / Partial / Missing stat boxes with Filament's `success` / `warning` / `danger` colors and heroicons); body is a Filament `Table` over `ProductType` with sortable/searchable/paginated columns and a `ViewAction` that opens a slide-over with the per-type breakdown. No bespoke Tailwind grids in the page view.
- **Navigation nests under Products.** `getNavigationParentItem()` returns `lunarpanel::product.plural_label`, so the entry shows up as **Products → Schema Health** instead of a top-level Catalog item.
- All admin-facing strings extracted to translation files. The page, widget, table columns, and slide-over now go through `__('lunar-product-schemas::filament.…')`.

### Added
- Translation files at `resources/lang/{en,pl}/filament.php` (English is the default; Polish ships out of the box). Publish via `php artisan vendor:publish --tag=lunar-product-schemas-translations` to override or add new locales.
- README **Translations** section documenting the locale fallback chain and how to publish / add languages.

## [1.2.0] - 2026-04-28

### Added
- **Schema Health** Filament page (opt-in via `LunarProductSchemasPlugin`). Shows per-ProductType completeness against the `required` attributes you've declared: total products, complete / partial / missing counts, complete-percentage bar, and a per-attribute breakdown of where the gaps are. Click any attribute → drill-down list of incomplete products.
- `SchemaHealthReport` service exposing the same data programmatically via `compute()`, `forType($handle)`, and `productsMissing($typeHandle, $attributeHandle)`. Returns `ProductTypeHealth` value objects.
- Filament moved to `require-dev` and listed under `suggest`. The package's runtime API and CLI commands keep working without it; only the Schema Health admin page is gated behind the plugin.
- 10 new tests covering the report service against real ProductType / Attribute / Product fixtures (83 / 193 total, all green).

## [1.1.3] - 2026-04-27

### Fixed
- `variantAttribute()` no longer crashes with `UniqueConstraintViolationException` when the supplied `group` handle already exists for product-level attributes (or vice versa). Lunar's `lunar_attribute_groups.handle` has a **global** unique constraint, not scoped by `attributable_type`; the package now resolves groups by handle alone and reuses any existing row, only setting `attributable_type` on first create.

### Added
- 3 new tests covering cross-attributable-type group reuse: variant↔product groups under the same handle are shared; the original `attributable_type` set on first create is preserved.

## [1.1.2] - 2026-04-27

### Added
- 5 new tests pinning down flag semantics on `variantAttribute()`: explicit flags persist, tristate leaves existing flags alone, explicit `false` overrides existing `true`, `ProductSchema::variantAttribute(...)->filterable()/searchable()/required()` chain works, and product-typed and variant-typed attributes sharing a handle keep independent flag state.

## [1.1.1] - 2026-04-27

### Documentation
- README: expanded the "Variant-level attributes" section with explicit examples of `filterable` / `searchable` / `required` flags on `variantAttribute()` and a reminder of the tristate (`null` = leave alone) semantics.

## [1.1.0] - 2026-04-27

### Added
- `ProductTypeBuilder::variantAttribute()` — define variant-level attributes (`attribute_type='variant'`), values land in `ProductVariant.attribute_data` JSON. Use cases: lead time, batch number, pantone code, manufacturer SKU.
- `ProductTypeBuilder::dropVariantAttribute()` — per-type drop with chunked cleanup of `ProductVariant.attribute_data` keys.
- `ProductTypeBuilder::syncVariantAttributes()` — authoritative variant-attribute set per type, independent of `syncAttributes()`.
- `ProductTypesBuilder::variantAttribute()`, `dropVariantAttribute()`, `syncVariantAttributes()` — fan-out across multiple types.
- `ProductSchema::variantAttribute()` — global builder for variant-level attribute operations (rename, flag toggles).
- 15 new feature tests covering variant-attribute lifecycle on both single-type and multi-type builders.

### Changed
- `ProductSchema::dropAttribute()` and `AttributeBuilder::rename()` now auto-detect `attribute_type` and clean up the correct `attribute_data` JSON layer (Product vs ProductVariant).
- `AttributeBuilder` constructor accepts an optional second argument `$attributableType` so the same class can drive both product-level and variant-level operations.

## [1.0.0] - 2026-04-25

### Added
- `ProductSchema` static entry point with `productType()`, `productTypes()`, `attribute()`, `dropAttribute()`, `dropProductType()`.
- `ProductTypeBuilder` — create/update product types, attach attributes with localized names and tristate flags (`searchable` / `filterable` / `required`), `dropAttribute()`, `syncAttributes()`, `rename()`.
- `ProductTypesBuilder` — fan out the same attribute schema to multiple product types, with `only()` to scope subsequent calls.
- `AttributeBuilder` — toggle flags globally, append translated names, `rename()` with automatic migration of `attribute_data` JSON keys across products.
- `product-schema:make`, `product-schema:apply`, `product-schema:rollback`, `product-schema:status` commands with their own `product_schema_migrations` tracking table (separate from Laravel's `migrations`).
- Bundled migration to add the `handle` column to `lunar_product_types` (Lunar core ships without it).
- Full feature test suite covering builders, static API, and console commands (Orchestra Testbench, runs against in-memory SQLite locally and MySQL 8 in CI).

[Unreleased]: https://github.com/wizcodepl/lunar-product-schemas/compare/v1.2.1...HEAD
[1.2.1]: https://github.com/wizcodepl/lunar-product-schemas/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/wizcodepl/lunar-product-schemas/compare/v1.1.3...v1.2.0
[1.1.3]: https://github.com/wizcodepl/lunar-product-schemas/compare/v1.1.2...v1.1.3
[1.1.2]: https://github.com/wizcodepl/lunar-product-schemas/compare/v1.1.1...v1.1.2
[1.1.1]: https://github.com/wizcodepl/lunar-product-schemas/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/wizcodepl/lunar-product-schemas/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/wizcodepl/lunar-product-schemas/releases/tag/v1.0.0
