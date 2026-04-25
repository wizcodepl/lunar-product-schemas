# Contributing

Thanks for your interest. PRs and issues are welcome.

## Reporting bugs

Please open a GitHub issue with:
- Lunar core version
- Laravel version
- PHP version
- A minimal reproduction (the smallest schema definition file or test that triggers the bug)
- Expected vs. actual behavior

If you suspect a security issue, do **not** open a public issue — see [SECURITY.md](.github/SECURITY.md).

## Proposing changes

1. Fork and create a topic branch off `main`.
2. Add or update tests covering the change. Tests live in `tests/Feature/` and use Orchestra Testbench against an in-memory SQLite database.
3. Run the suite:
   ```bash
   composer install
   vendor/bin/phpunit
   ```
4. Run the formatter:
   ```bash
   vendor/bin/pint
   ```
5. Update `CHANGELOG.md` under `[Unreleased]`.
6. Open a PR. Keep the description focused on **what** changed and **why** — link any related issue.

## Scope

This package is intentionally narrow: product-level `Attribute` rows, `AttributeGroup` membership, and the `ProductType ↔ Attribute` pivot. Variant axes (`ProductOption` / `ProductOptionValue`) are explicitly out of scope — see the README's "Out of scope" section. PRs that broaden the scope are welcome but please open an issue first to discuss the design.

## Code style

The project uses [Laravel Pint](https://laravel.com/docs/pint). The CI runs `pint --test` on every PR.
