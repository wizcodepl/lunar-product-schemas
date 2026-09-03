<p align="center">
  <img src="art/logo.svg" alt="Lunar Product Schemas" width="200">
</p>

# Lunar Product Schemas

[![Latest Version on Packagist](https://img.shields.io/packagist/v/wizcodepl/lunar-product-schemas.svg?style=flat-square)](https://packagist.org/packages/wizcodepl/lunar-product-schemas)
[![Tests](https://img.shields.io/github/actions/workflow/status/wizcodepl/lunar-product-schemas/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/wizcodepl/lunar-product-schemas/actions/workflows/tests.yml)
[![License](https://img.shields.io/packagist/l/wizcodepl/lunar-product-schemas.svg?style=flat-square)](LICENSE)

Migration-style schema builder for [Lunar](https://lunarphp.io) product types and attributes. Manage `searchable` / `filterable` / `required` flags, attach or detach attributes per product type (both **product-level** and **variant-level**), rename or drop attributes (with cleanup of values stored in `attribute_data` JSON on either layer) — all from versioned definition files that ship with your code.

> **Kompatybilność z Lunarem:** linia **`1.x`** celuje w **Lunar v1** (`lunarphp/core ^1.3`);
> linia **`2.x`** celuje w **Lunar v2** (`lunarphp/core ^2.0`, PHP 8.4, Laravel 12/13).
> Lunar v2 jest jeszcze w fazie alpha, więc do czasu stabilnego wydania instaluj
> `wizcodepl/lunar-product-schemas:^2.0@beta` razem z `lunarphp/core:^2.0@alpha`
> (albo ustaw `minimum-stability` w projekcie). W projektach v1 instaluj `^1.0`.

Inspired by Laravel's `Schema::table()` builder, but for the catalog layer Lunar exposes through `Attribute`, `AttributeGroup`, and `ProductType`.

## Why

Lunar lets you toggle `Attribute::filterable` / `searchable` / `required` from the admin panel. That works, but on a real shop you typically want:

- Attribute structure tracked in **code**, not panel clicks.
- A clear **history** of changes (who, when, why).
- Repeatable, environment-agnostic deploys.

Doing this with raw `Attribute::where(...)->update(...)` calls in Laravel migrations works, but quickly turns into copy-pasted boilerplate. This package is the thin wrapper that makes those operations readable, plus a dedicated `product-schema:*` command set so catalog changes don't fight Laravel's own `migrations` table for history.

## Requirements

- PHP 8.4+
- Lunar core ^2.0 (which itself pulls in Laravel 12 or 13)

## Install

```bash
composer require wizcodepl/lunar-product-schemas
```

The service provider auto-registers via Laravel package discovery.

Run `migrate` once to create the tracking table the package ships with:

```bash
php artisan migrate
```

This creates `product_schema_migrations` (separate from Laravel's own `migrations` table) so DB schema changes and product-catalog changes don't share batch numbers.

(Optional) publish the config to override the path where definitions live:

```bash
php artisan vendor:publish --tag=lunar-product-schemas-config
```

```php
// config/lunar-product-schemas.php
return [
    'path' => database_path('product-schemas'),

    // Optional: throw `UnknownAttributeException` when a Product / ProductVariant
    // is saved with `attribute_data` keys not declared in the product type's schema.
    'strict_mode' => env('LUNAR_PRODUCT_SCHEMAS_STRICT', false),

    // Optional: throw `MissingRequiredAttributeException` when a Product / ProductVariant
    // is saved without values for attributes marked `required: true` in its schema.
    'enforce_required' => env('LUNAR_PRODUCT_SCHEMAS_ENFORCE_REQUIRED', false),
];
```

## Strict mode

Enable `strict_mode` (config or `LUNAR_PRODUCT_SCHEMAS_STRICT=true` in `.env`) and the package observes Lunar's `Product` and `ProductVariant` saves: any `attribute_data` key not declared in the product type's schema throws `WizcodePl\LunarProductSchemas\Exceptions\UnknownAttributeException`. The schema becomes the source of truth — schema drift surfaces as a loud failure instead of silently corrupting `attribute_data` JSON.

Off by default so adopting the package on an existing catalog is a no-op; flip on once your schemas cover everything you actually persist.

> **Lunar v2 caveat:** `attribute_data` is keyed by attribute **id**, and Lunar's `AsAttributeData` cast silently discards any handle that matches no `Attribute` row at all — before any observer runs. Strict mode therefore catches attributes that **exist but aren't mapped to the product type** (the realistic drift case); a handle that was never declared anywhere is dropped by Lunar itself, not rejected.

## Enforce required

Enable `enforce_required` (config or `LUNAR_PRODUCT_SCHEMAS_ENFORCE_REQUIRED=true` in `.env`) and the same observers reject any save where attributes marked `required: true` in the schema are missing or empty (`null`, `''`, empty list/dict on a Lunar `FieldType`). They throw `WizcodePl\LunarProductSchemas\Exceptions\MissingRequiredAttributeException` listing the missing handles.

Independent from `strict_mode` — turn either or both on. Off by default because back-filling required values across an existing catalog can break unrelated workflows (admin edits, programmatic saves) until every record is migrated.

## Concepts: product-level vs variant-level attributes

Lunar stores attribute values on two layers, and this package manages both:

| Where the value lives | Use it for | Lunar admin tab | This package |
|---|---|---|---|
| `Product.attribute_data` JSON | Same value for all variants of one product (e.g. material, season, gender) | "Product Attributes" | `attribute()` |
| `ProductVariant.attribute_data` JSON | Per-SKU descriptive data the customer doesn't pick (e.g. lead time, pantone code, batch number) | "Variant Attributes" | `variantAttribute()` |

What this package does **not** manage: customer-pickable variant axes like Size and Color — those are Lunar's separate `ProductOption` / `ProductOptionValue` mechanism. See **Out of scope** below.

## Quick start

Create a definition file:

```bash
php artisan product-schema:make add_t_shirt_attributes
# → database/product-schemas/2026_05_01_120000_add_t_shirt_attributes.php
```

Fill it in:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use WizcodePl\LunarProductSchemas\ProductSchema;

return new class extends Migration
{
    public function up(): void
    {
        ProductSchema::productType('t-shirts', 'T-shirts')
            // Product-level: same value across all variants of a given t-shirt.
            ->attribute('material',  name: 'Material',  filterable: true, required: true)
            ->attribute('season',    name: 'Season',    filterable: true)
            ->attribute('gender',    name: 'Gender',    filterable: true)
            // Variant-level: per-SKU data the customer doesn't pick.
            ->variantAttribute('lead_time_days', name: 'Lead time (days)')
            ->variantAttribute('batch_number',   name: 'Batch number');
    }

    public function down(): void
    {
        ProductSchema::productType('t-shirts')
            ->dropVariantAttribute('batch_number')
            ->dropVariantAttribute('lead_time_days')
            ->dropAttribute('gender')
            ->dropAttribute('season')
            ->dropAttribute('material');
    }
};
```

Apply it:

```bash
php artisan product-schema:apply
```

## Commands

| Command                       | What it does                                                            |
|-------------------------------|-------------------------------------------------------------------------|
| `product-schema:make {name}`  | Generate a new timestamped definition file from the stub.               |
| `product-schema:apply`        | Run all pending definition files, recording each in `product_schema_migrations`. |
| `product-schema:rollback`     | Roll back the most recent batch (or `--step=N` to roll back N batches). |
| `product-schema:status`       | List every definition file with `Pending` or `Ran [batch N]`.           |

`apply` and `rollback` accept `--pretend` to print SQL without executing.

## Usage

### Single product type

```php
use WizcodePl\LunarProductSchemas\ProductSchema;

ProductSchema::productType('t-shirts', 'T-shirts')
    ->attribute('material',  name: 'Material',  filterable: true, required: true)
    ->attribute('season',    name: 'Season',    filterable: true)
    ->variantAttribute('lead_time_days', name: 'Lead time (days)');
```

### Many product types in one chain

When several product types share an attribute set, fan out with `productTypes()`:

```php
ProductSchema::productTypes([
    't-shirts' => 'T-shirts',
    'shoes'    => 'Shoes',
    'bags'     => 'Bags',
])
    ->attribute('material',         filterable: true, required: true)
    ->attribute('season',           filterable: true)
    ->variantAttribute('lead_time_days')
    ->variantAttribute('batch_number');
```

Pass a flat list when names match the handles:

```php
ProductSchema::productTypes(['t-shirts', 'shoes', 'bags'])
    ->attribute('material', filterable: true);
```

Restrict subsequent calls to a subset:

```php
ProductSchema::productTypes(['t-shirts', 'shoes', 'bags'])
    ->attribute('material', filterable: true)         // applied to all three
    ->only('t-shirts', 'bags')
        ->attribute('pattern');                       // only t-shirts and bags
```

### Variant-level attributes

Per-SKU data the customer doesn't pick — lead time, batch number, pantone code, supplier ID, manufacturer SKU. Values land in `ProductVariant.attribute_data` JSON.

```php
ProductSchema::productType('t-shirts')
    ->variantAttribute('lead_time_days',   name: 'Lead time (days)')
    ->variantAttribute('batch_number',     name: 'Batch number')
    ->variantAttribute('pantone_code',     name: 'Pantone code');
```

`variantAttribute()` takes the same flags as `attribute()` — `filterable`, `searchable`, `required` — wired through to the underlying `Attribute` row:

```php
ProductSchema::productType('t-shirts')
    ->variantAttribute(
        handle: 'manufacturer_sku',
        name: 'Manufacturer SKU',
        searchable: true,
        required: true,                              // every variant must carry it
    )
    ->variantAttribute(
        handle: 'lead_time_days',
        name: 'Lead time (days)',
        filterable: true,                            // facet on the storefront
    )
    ->variantAttribute(
        handle: 'pantone_code',
        name: 'Pantone code',
        searchable: false,                           // internal, hide from search index
    );
```

Same tristate semantics: pass `null` (default) to leave an existing flag untouched, `true`/`false` to force.

These show up under the **"Variant Attributes"** tab in Lunar admin (Product Types → [t-shirts]).

> **Note:** if you want customers to *pick* a value (Size: S/M/L, Color: Red/Blue), that's a `ProductOption` — a different Lunar mechanism not handled here. See **Out of scope** below.

### Names

Lunar v2 stores attribute and attribute-group names as plain strings (no per-locale translations):

```php
ProductSchema::productType('t-shirts')
    ->attribute(
        handle: 'material',
        name: 'Material',
        groupName: 'Specifications',
        group: 'specifications',
        filterable: true,
        required: true,
    );
```

The v1 locale-keyed array (`name: ['en' => 'Material', 'pl' => 'Materiał']`) is still accepted so old definition files keep running: the entry for the current `app()->getLocale()` wins, falling back to the first one.

### Handles

Lunar v2 slugs attribute and group handles on save (`Str::slug($handle, '_')`), so `Lead-Time` is stored as `lead_time`. The package applies the same rule to every lookup, so you can use either form consistently — but the stored handle (the one you read back in `attribute_data`) is always the slugged one. Handles are **globally unique** in v2: one row per handle, mappable to the product layer, the variant layer, or both.

### Field type configuration

Lunar's admin panel reads `Attribute::configuration` JSON to choose the right form component (e.g. `richtext: true` makes `Text` / `TranslatedText` render as a WYSIWYG editor instead of a single-line input). Pass `configuration: [...]` on the schema definition and the package writes it straight onto the attribute row:

```php
use Lunar\Core\Enums\FieldTypeEnum;

ProductSchema::productType('t-shirts')
    ->attribute(
        handle: 'description',
        name: 'Description',
        type: FieldTypeEnum::TranslatedText,
        configuration: ['richtext' => true],
        required: true,
    );
```

`type` accepts a `FieldTypeEnum` case, a field-type key (`'text'`, `'list'`, …) or a `FieldType` class string (`TranslatedText::class`, resolved through Lunar's `FieldTypeManifest`, so custom field types work too). Lunar v2 stores the **key** on `Attribute::type`. Defaults to `text`.

`configuration` follows the same null-leaves-existing-alone semantics as the boolean flags — re-running a migration without it preserves whatever's already stored.

### Renaming and toggling flags globally

`ProductSchema::attribute(...)` operates on a product-level attribute regardless of which product types use it. `ProductSchema::variantAttribute(...)` is the equivalent for variant-level attributes.

```php
// flip flags on a product-level attribute
ProductSchema::attribute('material')->filterable(true)->required(false);

// rename handle — Lunar v2 keys attribute_data by attribute id, so stored
// product/variant values follow the new handle with no data rewrite
ProductSchema::attribute('material')->rename('fabric');

// rename a variant-level attribute
ProductSchema::variantAttribute('lead_time_days')->rename('processing_days');

// display name
ProductSchema::attribute('material')->name('Materiał');
```

Because a handle is one row in v2, `ProductSchema::attribute('x')` and `ProductSchema::variantAttribute('x')` address the **same** attribute; the two entry points only differ in which model-type mapping they require to exist (and throw `ModelNotFoundException` otherwise).

### Dropping attributes

Per product type — keeps the attribute alive for other types still using it, but strips the value from this type's products (or variants):

```php
// product-level
ProductSchema::productType('shoes')
    ->dropAttribute('lining');

// variant-level
ProductSchema::productType('t-shirts')
    ->dropVariantAttribute('batch_number');
```

Globally — deletes the attribute row:

```php
ProductSchema::dropAttribute('legacy_color_code');
```

Lunar v2 does the rest: deleting an `Attribute` cascades the `attribute_models` and `product_type_attribute` pivots, and Lunar's own observer dispatches a `PurgeAttributeData` job that strips the id key from every `Product` / `ProductVariant` `attribute_data` (on your queue, if one is configured). Since `attribute_data` is id-keyed, the value is invisible to the model cast the moment the row is gone — the job only reclaims JSON bytes.

### Authoritative attribute set per type

Detach every attribute whose handle is **not** in the list. The product-level and variant-level lists are independent — `syncAttributes()` doesn't touch variant attrs, and `syncVariantAttributes()` doesn't touch product attrs.

```php
ProductSchema::productType('t-shirts')
    ->syncAttributes(['material', 'season', 'gender'])               // product-level
    ->syncVariantAttributes(['lead_time_days', 'batch_number']);     // variant-level
```

### Dropping a product type

```php
ProductSchema::dropProductType('legacy-products');
```

Lunar cascades the `ProductType ↔ Attribute` pivot. Lunar v2 **refuses** to delete a product type that still has products (`Lunar\Core\Exceptions\ProductTypeActionException`) — reassign or remove them first. A missing handle is a no-op.

## Schema Health (Spatie health check)

The package ships a [`spatie/laravel-health`](https://github.com/spatie/laravel-health) check that reports how complete your catalog is against the `required` attributes you've declared. Drop it into your `HealthServiceProvider`:

```php
use Spatie\Health\Facades\Health;
use WizcodePl\LunarProductSchemas\Health\ProductSchemaHealthCheck;

Health::checks([
    ProductSchemaHealthCheck::new()
        ->minCompletePercentage(95.0)   // below this → failed
        ->warningCompletePercentage(85), // below this → warning
]);
```

The check walks every `ProductType` and inspects `attribute_data` on each `Product` — same logic, just exposed through the Spatie health pipeline so it surfaces on whichever dashboard / endpoint / notifier you already have wired up. `meta` on the result includes `complete_percentage`, totals, and a per-ProductType breakdown for drill-down.

Install Spatie Health if your app doesn't already use it:

```bash
composer require spatie/laravel-health
```

The Filament admin page that shipped with the `1.x` line was removed in `1.5.1` and does not apply to Lunar v2 (whose back office is `lunarphp/panel`, not Filament). The patch in [`patches/0001-filament-schema-health.patch`](patches/0001-filament-schema-health.patch) is kept for `1.x` users only.

## Out of scope

This package covers **`Attribute` schema** on both product and variant layers. It does **not** wrap:

- `ProductOption` / `ProductOptionValue` — the customer-pickable variant axes (Size, Color). Those are typically generated at sync time from external systems (vendor APIs, ERP exports, marketplace feeds) with vendor-specific identifiers, not declared statically in code.
- Generating `ProductVariant` rows per option combination — also sync-time / vendor-specific.

If your shop has a curated catalog where variant axes are stable design decisions and you'd like tooling for them, open a GitHub issue — happy to discuss.

## Notes

- Operations are idempotent where possible: re-running a definition that creates an attribute already in the DB is a no-op-with-update.
- Flag parameters are tristate — `null` (default) means "leave the existing value alone".
- The package uses `saveQuietly()` when modifying products and variants in bulk so observers (e.g. Scout) don't fire one-by-one. Re-index in bulk after applying definitions if needed.
- The `required` / `filterable` / `searchable` flags live on the `Attribute` itself, so they're effectively global — flipping one for one product type flips it everywhere. In Lunar v2 the same goes for an attribute used on both the product and variant layer (one row, shared flags).

## Testing

```bash
composer install
vendor/bin/phpunit
```

Tests run against a real `lunarphp/core` 2.x install via Orchestra Testbench, with Lunar core's migrations and this package's tracking-table migration applied automatically. PHP 8.4+ is required (`/usr/local/opt/php@8.4/bin/php` on a Homebrew Mac with a lower default PHP). The default driver is in-memory SQLite (zero setup), and CI also runs the suite against MySQL 8 to catch JSON-column behavior that SQLite glosses over.

Switch the local run to MySQL by exporting `DB_CONNECTION=mysql` and the standard `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` variables before invoking `phpunit`.

Code style is enforced with [Laravel Pint](https://laravel.com/docs/pint):

```bash
vendor/bin/pint           # auto-fix
vendor/bin/pint --test    # check only (CI uses this)
```

## About Wizcode

[Wizcode](https://wizcode.pl) is an e-commerce agency specialised in [Lunar](https://lunarphp.io). We design and ship B2B, B2C, and marketplace platforms on the Laravel + Lunar stack — from custom checkouts and supplier syncs to multi-channel pricing, PIM workflows, and headless storefronts.

Our open-source contributions to the Lunar ecosystem:

- [wizcodepl/lunar-product-schemas](https://github.com/wizcodepl/lunar-product-schemas) — migration-style schema builder for Lunar product types and attributes.
- [wizcodepl/laravel-pipe](https://github.com/wizcodepl/laravel-pipe) — stage-based pipeline framework for batch ETL of supplier feeds (used in production for catalog ingestion).

Contact us: [https://wizcode.pl](https://wizcode.pl)

## License

MIT — see [LICENSE](LICENSE).
