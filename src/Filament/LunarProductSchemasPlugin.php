<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use WizcodePl\LunarProductSchemas\Filament\Pages\SchemaHealth;

/**
 * Filament plugin — opt-in. Register in your PanelProvider:
 *
 *   $panel->plugin(\WizcodePl\LunarProductSchemas\Filament\LunarProductSchemasPlugin::make());
 *
 * Without this, the package's runtime API and CLI commands work as before;
 * only the Schema Health admin page is gated behind the plugin so panels
 * that don't want it stay clean.
 */
class LunarProductSchemasPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'lunar-product-schemas';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            SchemaHealth::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        // No additional boot hooks needed for v1.2.
    }
}
