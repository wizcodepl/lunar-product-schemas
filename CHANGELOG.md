# Changelog

All notable changes to `wizcodepl/lunar-product-schemas` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-04-25

### Added
- `ProductSchema` static entry point with `productType()`, `productTypes()`, `attribute()`, `dropAttribute()`, `dropProductType()`.
- `ProductTypeBuilder` — create/update product types, attach attributes with localized names and tristate flags (`searchable` / `filterable` / `required`), `dropAttribute()`, `syncAttributes()`, `rename()`.
- `ProductTypesBuilder` — fan out the same attribute schema to multiple product types, with `only()` to scope subsequent calls.
- `AttributeBuilder` — toggle flags globally, append translated names, `rename()` with automatic migration of `attribute_data` JSON keys across products.
- `product-schema:make`, `product-schema:apply`, `product-schema:rollback`, `product-schema:status` commands with their own `product_schema_migrations` tracking table (separate from Laravel's `migrations`).
- Bundled migration to add the `handle` column to `lunar_product_types` (Lunar core ships without it).
- Full feature test suite covering builders, static API, and console commands (Orchestra Testbench, runs against in-memory SQLite locally and MySQL 8 in CI).

[Unreleased]: https://github.com/wizcodepl/lunar-product-schemas/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/wizcodepl/lunar-product-schemas/releases/tag/v1.0.0
