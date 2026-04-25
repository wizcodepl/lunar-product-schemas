<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas\Migrations;

use Illuminate\Database\Migrations\DatabaseMigrationRepository;

/**
 * Empty subclass to give the container a unique type to bind against.
 * Behaves identically to Laravel's repository — only the table name differs (set in the service provider).
 */
class ProductSchemaMigrationRepository extends DatabaseMigrationRepository {}
