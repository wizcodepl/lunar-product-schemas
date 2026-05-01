# Lunar Product Schemas

[![Latest Version on Packagist](https://img.shields.io/packagist/v/wizcodepl/lunar-product-schemas.svg?style=flat-square)](https://packagist.org/packages/wizcodepl/lunar-product-schemas)
[![Tests](https://img.shields.io/github/actions/workflow/status/wizcodepl/lunar-product-schemas/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/wizcodepl/lunar-product-schemas/actions/workflows/tests.yml)
[![License](https://img.shields.io/packagist/l/wizcodepl/lunar-product-schemas.svg?style=flat-square)](LICENSE)

Migration-style schema builder for [Lunar](https://lunarphp.io) product types and attributes. Manage `searchable` / `filterable` / `required` flags, attach or detach attributes per product type (both **product-level** and **variant-level**), rename or drop attributes (with cleanup of values stored in `attribute_data` JSON on either layer) — all from versioned definition files that ship with your code.

Inspired by Laravel's `Schema::table()` builder, but for the catalog layer Lunar exposes through `Attribute`, `AttributeGroup`, and `ProductType`.

## Why

Lunar lets you toggle `Attribute::filterable` / `searchable` / `required` from the Filament admin panel. That works, but on a real shop you typically want:

- Attribute structure tracked in **code**, not panel clicks.
- A clear **history** of changes (who, when, why).
- Repeatable, environment-agnostic deploys.

Doing this with raw `Attribute::where(...)->update(...)` calls in Laravel migrations works, but quickly turns into copy-pasted boilerplate. This package is the thin wrapper that makes those operations readable, plus a dedicated `product-schema:*` command set so catalog changes don't fight Laravel's own `migrations` table for history.

## Requirements

- PHP 8.2+
- Lunar core ^1.3 (which itself pulls in Laravel 11 or 12)

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
];
```

## Strict mode

Enable `strict_mode` (config or `LUNAR_PRODUCT_SCHEMAS_STRICT=true` in `.env`) and the package observes Lunar's `Product` and `ProductVariant` saves: any `attribute_data` key not declared in the product type's schema throws `WizcodePl\LunarProductSchemas\Exceptions\UnknownAttributeException`. The schema becomes the source of truth — schema drift surfaces as a loud failure instead of silently corrupting `attribute_data` JSON.

Off by default so adopting the package on an existing catalog is a no-op; flip on once your schemas cover everything you actually persist.

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
            ->attribute('material',  name: ['en' => 'Material'],  filterable: true, required: true)
            ->attribute('season',    name: ['en' => 'Season'],    filterable: true)
            ->attribute('gender',    name: ['en' => 'Gender'],    filterable: true)
            // Variant-level: per-SKU data the customer doesn't pick.
            ->variantAttribute('lead_time_days', name: ['en' => 'Lead time (days)'])
            ->variantAttribute('batch_number',   name: ['en' => 'Batch number']);
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
    ->attribute('material',  name: ['en' => 'Material'],  filterable: true, required: true)
    ->attribute('season',    name: ['en' => 'Season'],    filterable: true)
    ->variantAttribute('lead_time_days', name: ['en' => 'Lead time (days)']);
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
    ->variantAttribute('lead_time_days',   name: ['en' => 'Lead time (days)'])
    ->variantAttribute('batch_number',     name: ['en' => 'Batch number'])
    ->variantAttribute('pantone_code',     name: ['en' => 'Pantone code']);
```

`variantAttribute()` takes the same flags as `attribute()` — `filterable`, `searchable`, `required` — wired through to the underlying `Attribute` row:

```php
ProductSchema::productType('t-shirts')
    ->variantAttribute(
        handle: 'manufacturer_sku',
        name: ['en' => 'Manufacturer SKU'],
        searchable: true,
        required: true,                              // every variant must carry it
    )
    ->variantAttribute(
        handle: 'lead_time_days',
        name: ['en' => 'Lead time (days)'],
        filterable: true,                            // facet on the storefront
    )
    ->variantAttribute(
        handle: 'pantone_code',
        name: ['en' => 'Pantone code'],
        searchable: false,                           // internal, hide from search index
    );
```

Same tristate semantics: pass `null` (default) to leave an existing flag untouched, `true`/`false` to force.

These show up under the **"Variant Attributes"** tab in Lunar admin (Product Types → [t-shirts]).

> **Note:** if you want customers to *pick* a value (Size: S/M/L, Color: Red/Blue), that's a `ProductOption` — a different Lunar mechanism not handled here. See **Out of scope** below.

### Localized names

Pass a string for the current `app()->getLocale()`, or an array keyed by locale for multilingual setups:

```php
ProductSchema::productType('t-shirts')
    ->attribute(
        handle: 'material',
        name: ['en' => 'Material', 'pl' => 'Materiał'],
        groupName: ['en' => 'Specifications', 'pl' => 'Specyfikacja'],
        group: 'specifications',
        filterable: true,
        required: true,
    );
```

### Field type configuration

Lunar's Filament admin reads `Attribute::configuration` JSON to choose the right form component (e.g. `richtext: true` makes `Text` / `TranslatedText` render as a WYSIWYG editor instead of a single-line input). Pass `configuration: [...]` on the schema definition and the package writes it straight onto the attribute row:

```php
ProductSchema::productType('t-shirts')
    ->attribute(
        handle: 'description',
        name: ['en' => 'Description', 'pl' => 'Opis'],
        type: TranslatedText::class,
        configuration: ['richtext' => true],
        required: true,
    );
```

`configuration` follows the same null-leaves-existing-alone semantics as the boolean flags — re-running a migration without it preserves whatever's already stored.

### Renaming and toggling flags globally

`ProductSchema::attribute(...)` operates on a product-level attribute regardless of which product types use it. `ProductSchema::variantAttribute(...)` is the equivalent for variant-level attributes.

```php
// flip flags on a product-level attribute
ProductSchema::attribute('material')->filterable(true)->required(false);

// rename handle (chunked migration of attribute_data JSON keys across every product)
ProductSchema::attribute('material')->rename('fabric');

// rename a variant-level attribute (chunked migration across every ProductVariant)
ProductSchema::variantAttribute('lead_time_days')->rename('processing_days');

// localized label
ProductSchema::attribute('material')->name('Materiał', locale: 'pl');
```

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

Globally — detaches from every product type, strips values from every product or variant (auto-detected from the attribute's type), then deletes the attribute row:

```php
ProductSchema::dropAttribute('legacy_color_code');
```

Lunar's polymorphic pivot (`lunar_attributables`) lacks cascade; the package wipes those pivot rows for you.

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

Lunar cascades the `ProductType ↔ Attribute` pivot. Products of this type are **not** deleted — orphaning them is rarely what you want, so migrate the data explicitly first.

## Schema Health (Filament)

A bundled Filament admin page surfaces **how complete your catalog actually is** against the `required` attributes you've declared. Lives under **Catalog → Products → Schema Health** (sibling of *Product Types*), so it's right where someone looking at the catalog model would expect it.

Opt in by registering the plugin in your `PanelProvider`:

```php
use WizcodePl\LunarProductSchemas\Filament\LunarProductSchemasPlugin;

public function panel(Panel $panel): Panel
{
    return $panel->plugin(LunarProductSchemasPlugin::make());
}
```

What you get:

- **Header stats widget** — three native Filament `StatsOverviewWidget` cards aggregating the whole catalog: Complete / Partial / Missing.
- **Filament Table** of every ProductType with: name, total products, completeness %, complete / partial / missing counts. Searchable, sortable, paginated.
- **Click a row** → slide-over with the per-type breakdown:
  - Three stat boxes for that type
  - Progress bar with exact %
  - Required-fields list
  - Per-attribute gap breakdown ("23 products missing `material`, 8 missing `gtin`")
  - Each gap is **collapsible** — expand to see the actual list of products that lack the field

It uses only what Lunar already exposes — the `required` flag on `Attribute` and `attribute_data` on `Product`. No new tables, no new concepts, no extra configuration. The moment you mark an attribute `required: true` (via this package or otherwise), it lights up in the dashboard.

If you don't install Filament or don't register the plugin, the rest of the package works as before — the report data is also available programmatically:

```php
use WizcodePl\LunarProductSchemas\Reports\SchemaHealthReport;

$rows = app(SchemaHealthReport::class)->compute();
foreach ($rows as $row) {
    $row->productType;                  // Lunar ProductType
    $row->totalProducts;                // int
    $row->complete;                     // int
    $row->partial;                      // int
    $row->missing;                      // int
    $row->completePercentage();         // float
    $row->requiredAttributeHandles;     // ['material', 'gtin']
    $row->missingByAttribute;           // ['material' => 23, 'gtin' => 8]
}

// Health for a single ProductType by handle:
$health = app(SchemaHealthReport::class)->forType('t-shirts');

// Drill-down for a specific (type, attribute) pair:
$incomplete = app(SchemaHealthReport::class)
    ->productsMissing('t-shirts', 'material');
```

## Translations

The Filament page ships with **English (default) and Polish** out of the box. The widget, table columns, slide-over content and action labels all go through Laravel's translation system.

For other locales, or to customise the wording, publish the translation files into your app:

```bash
php artisan vendor:publish --tag=lunar-product-schemas-translations
```

This copies the bundled translations to `lang/vendor/lunar-product-schemas/{en,pl}/filament.php` where you can edit them directly. Adding a new locale (say German):

```bash
cp lang/vendor/lunar-product-schemas/en/filament.php \
   lang/vendor/lunar-product-schemas/de/filament.php
# translate the values, set app.locale = 'de'
```

Laravel's standard fallback chain applies — if a key is missing in the active locale, it falls back to `config('app.fallback_locale')` (English by default), so partial translations are safe.

## Out of scope

This package covers **`Attribute` schema** on both product and variant layers. It does **not** wrap:

- `ProductOption` / `ProductOptionValue` — the customer-pickable variant axes (Size, Color). Those are typically generated at sync time from external systems (vendor APIs, ERP exports, marketplace feeds) with vendor-specific identifiers, not declared statically in code.
- Generating `ProductVariant` rows per option combination — also sync-time / vendor-specific.

If your shop has a curated catalog where variant axes are stable design decisions and you'd like tooling for them, open a GitHub issue — happy to discuss.

## Notes

- Operations are idempotent where possible: re-running a definition that creates an attribute already in the DB is a no-op-with-update.
- Flag parameters are tristate — `null` (default) means "leave the existing value alone".
- The package uses `saveQuietly()` when modifying products and variants in bulk so observers (e.g. Scout) don't fire one-by-one. Re-index in bulk after applying definitions if needed.
- The `required` flag lives on the `Attribute` itself in Lunar 1.3, so it's effectively global — flipping it for one product type flips it everywhere.

## Testing

```bash
composer install
vendor/bin/phpunit
```

Tests run via Orchestra Testbench, with Lunar core's migrations and this package's `handle`-column migration applied automatically. The default driver is in-memory SQLite (zero setup), and CI also runs the suite against MySQL 8 to catch JSON-column behavior that SQLite glosses over.

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
