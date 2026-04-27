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
];
```

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

## API reference

### `ProductSchema::productType(string $handle, ?string $name = null): ProductTypeBuilder`

Creates the product type if missing. If `$name` is supplied and differs, updates it.

### `ProductSchema::productTypes(array $types): ProductTypesBuilder`

Either a flat list of handles or a `[handle => name]` map. Every method on the returned builder fans out to each underlying `ProductTypeBuilder`.

### `ProductSchema::attribute(string $handle): AttributeBuilder`

Cross-type operations on a **product-level** attribute. Throws if it doesn't exist.

### `ProductSchema::variantAttribute(string $handle): AttributeBuilder`

Cross-type operations on a **variant-level** attribute. Throws if it doesn't exist.

### `ProductSchema::dropAttribute(string $handle): void`

Global drop with full cleanup (pivot rows + correct `attribute_data` JSON layer based on the attribute's type).

### `ProductSchema::dropProductType(string $handle): void`

Deletes the product type row only.

### `ProductTypeBuilder::attribute(...)` / `ProductTypeBuilder::variantAttribute(...)`

```php
attribute(
    string $handle,
    string|array|null $name = null,
    ?string $type = null,
    string $group = 'spec',                  // 'variant_spec' for variantAttribute()
    string|array|null $groupName = null,
    ?bool $searchable = null,
    ?bool $filterable = null,
    ?bool $required = null,
)
```

Both are idempotent. Defaults (`type=Text`, `searchable=true`, `filterable=false`, `required=false`) are applied **only on first create**. Tristate flags (`null` = leave existing value alone) make it safe for multiple migrations to touch the same attribute without unintentionally resetting flags.

### `ProductTypeBuilder::dropAttribute(string $handle)` / `ProductTypeBuilder::dropVariantAttribute(string $handle)`

Detach + strip JSON values from this type only. Other product types are untouched. Each method targets its own layer.

### `ProductTypeBuilder::syncAttributes(array $keep)` / `ProductTypeBuilder::syncVariantAttributes(array $keep)`

Detach every attribute on the matching layer whose handle is not in `$keep`. Cross-layer attrs are untouched.

### `ProductTypeBuilder::rename(string $newHandle, ?string $newName = null)`

Rename the product type itself.

### `AttributeBuilder` methods

- `filterable(bool $value = true)`
- `searchable(bool $value = true)`
- `required(bool $value = true)`
- `name(string $name, string $locale = 'en')`
- `rename(string $newHandle)` — also migrates `attribute_data` JSON keys (chunked) on the correct layer (Product or ProductVariant) based on how the builder was constructed.

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

## License

MIT — see [LICENSE](LICENSE).
