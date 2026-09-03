<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas\Builders;

use Illuminate\Support\Str;
use Lunar\Core\Enums\FieldTypeEnum;
use Lunar\Core\Facades\FieldTypeManifest;
use Lunar\Core\Models\Attribute;
use Lunar\Core\Models\AttributeGroup;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductType;
use Lunar\Core\Models\ProductVariant;

class ProductTypeBuilder
{
    private ProductType $type;

    public function __construct(string $handle, ?string $name = null)
    {
        $resolvedName = $name ?? Str::headline($handle);

        $this->type = ProductType::firstOrCreate(
            ['handle' => $handle],
            ['name' => $resolvedName],
        );

        if ($name !== null && $this->type->name !== $name) {
            $this->type->update(['name' => $name]);
        }
    }

    public function attribute(
        string $handle,
        string|array|null $name = null,
        FieldTypeEnum|string|null $type = null,
        string $group = 'spec',
        string|array|null $groupName = null,
        ?bool $searchable = null,
        ?bool $filterable = null,
        ?bool $required = null,
        ?array $configuration = null,
    ): self {
        return $this->upsertAttribute(
            modelType: Product::morphName(),
            handle: $handle, name: $name, type: $type, group: $group, groupName: $groupName,
            searchable: $searchable, filterable: $filterable, required: $required, configuration: $configuration,
        );
    }

    public function variantAttribute(
        string $handle,
        string|array|null $name = null,
        FieldTypeEnum|string|null $type = null,
        string $group = 'variant_spec',
        string|array|null $groupName = null,
        ?bool $searchable = null,
        ?bool $filterable = null,
        ?bool $required = null,
        ?array $configuration = null,
    ): self {
        return $this->upsertAttribute(
            modelType: ProductVariant::morphName(),
            handle: $handle, name: $name, type: $type, group: $group, groupName: $groupName,
            searchable: $searchable, filterable: $filterable, required: $required, configuration: $configuration,
        );
    }

    public function dropAttribute(string $handle): self
    {
        return $this->detachAndStrip($handle, Product::morphName());
    }

    public function dropVariantAttribute(string $handle): self
    {
        return $this->detachAndStrip($handle, ProductVariant::morphName());
    }

    public function syncAttributes(array $keep): self
    {
        return $this->syncAttributesOfType(Product::morphName(), $keep);
    }

    public function syncVariantAttributes(array $keep): self
    {
        return $this->syncAttributesOfType(ProductVariant::morphName(), $keep);
    }

    public function rename(string $newHandle, ?string $newName = null): self
    {
        $this->type->update(array_filter([
            'handle' => $newHandle,
            'name' => $newName,
        ]));

        return $this;
    }

    public function model(): ProductType
    {
        return $this->type;
    }

    private function upsertAttribute(
        string $modelType,
        string $handle,
        string|array|null $name,
        FieldTypeEnum|string|null $type,
        string $group,
        string|array|null $groupName,
        ?bool $searchable,
        ?bool $filterable,
        ?bool $required,
        ?array $configuration,
    ): self {
        $handle = self::normalizeHandle($handle);
        $group = self::normalizeHandle($group);

        // Lunar v2: attribute groups are not bound to a model type and their
        // `handle` is globally unique, so product- and variant-level attributes
        // may share a group.
        $attributeGroup = AttributeGroup::firstOrCreate(
            ['handle' => $group],
            [
                'name' => self::displayName($groupName ?? Str::headline($group)),
                'position' => self::nextGroupPosition(),
            ],
        );

        // v2: `handle` is globally unique — one Attribute per handle, mapped to
        // one or more model types via the `attribute_models` pivot.
        $existing = Attribute::query()->where('handle', $handle)->first();

        $payload = array_filter(
            [
                'attribute_group_id' => $attributeGroup->id,
                'name' => $name !== null ? self::displayName($name) : null,
                'type' => self::fieldTypeKey($type),
                'searchable' => $searchable,
                'filterable' => $filterable,
                'required' => $required,
                'configuration' => $configuration,
            ],
            fn ($value) => $value !== null,
        );

        if ($existing === null) {
            $payload += [
                'name' => self::displayName(Str::headline($handle)),
                'type' => FieldTypeEnum::Text->value,
                'searchable' => true,
                'filterable' => false,
                'required' => false,
                'system' => false,
                'position' => ((int) Attribute::query()
                    ->where('attribute_group_id', $attributeGroup->id)
                    ->max('position')) + 1,
                'configuration' => [],
            ];
        }

        $attribute = Attribute::updateOrCreate(['handle' => $handle], $payload);

        // Ensure the model-type mapping (product / product_variant) exists.
        $attribute->models()->firstOrCreate(['model_type' => $modelType]);

        // Attach to this product type (idempotent) via product_type_attribute.
        $this->type->attributeMapping()->syncWithoutDetaching([$attribute->id]);

        return $this;
    }

    /**
     * Detach the attribute from this type and strip its stored values from
     * this type's products (or variants). The attribute row itself survives —
     * other product types may still map it; use ProductSchema::dropAttribute()
     * for a global drop.
     */
    private function detachAndStrip(string $handle, string $modelType): self
    {
        $handle = self::normalizeHandle($handle);

        $attribute = Attribute::query()
            ->where('handle', $handle)
            ->whereHas('models', fn ($query) => $query->where('model_type', $modelType))
            ->first();

        if (! $attribute) {
            return $this;
        }

        $this->type->attributeMapping()->detach($attribute->id);

        $isVariant = $modelType === ProductVariant::morphName();

        if ($isVariant) {
            ProductVariant::query()
                ->whereHas('product', fn ($query) => $query->where('product_type_id', $this->type->id))
                ->chunkById(500, fn ($rows) => self::stripKey($rows, $handle));
        } else {
            Product::query()
                ->where('product_type_id', $this->type->id)
                ->chunkById(500, fn ($rows) => self::stripKey($rows, $handle));
        }

        return $this;
    }

    private function syncAttributesOfType(string $modelType, array $keep): self
    {
        $keep = array_map(self::normalizeHandle(...), $keep);

        $keepIds = Attribute::query()
            ->whereIn('handle', $keep)
            ->whereHas('models', fn ($query) => $query->where('model_type', $modelType))
            ->pluck('id');

        $currentIds = $this->type->attributeMapping()
            ->whereHas('models', fn ($query) => $query->where('model_type', $modelType))
            ->get()
            ->pluck('id');

        $toDetach = $currentIds->diff($keepIds);
        if ($toDetach->isNotEmpty()) {
            $this->type->attributeMapping()->detach($toDetach->all());
        }

        $toAttach = $keepIds->diff($currentIds);
        if ($toAttach->isNotEmpty()) {
            $this->type->attributeMapping()->syncWithoutDetaching($toAttach->all());
        }

        return $this;
    }

    private static function stripKey(iterable $rows, string $handle): void
    {
        foreach ($rows as $row) {
            $data = $row->attribute_data;
            if ($data?->has($handle)) {
                $data->forget($handle);
                $row->attribute_data = $data;
                $row->saveQuietly();
            }
        }
    }

    /**
     * Lunar v2 slugs attribute / group handles on assignment
     * (`Str::slug($value, '_')`); apply the same rule up front so lookups by
     * the handle the caller wrote match what Lunar actually stored.
     */
    public static function normalizeHandle(string $handle): string
    {
        return Str::slug($handle, '_');
    }

    /**
     * Normalise a field-type reference to a v2 field-type key (e.g. `text`).
     * Accepts a FieldTypeEnum case, a key as-is, or a FieldType class string
     * (resolved through the FieldTypeManifest so custom types work too).
     */
    private static function fieldTypeKey(FieldTypeEnum|string|null $type): ?string
    {
        if ($type === null) {
            return null;
        }

        if ($type instanceof FieldTypeEnum) {
            return $type->value;
        }

        if (! str_contains($type, '\\')) {
            return $type;
        }

        $key = FieldTypeManifest::getTypes()->search(ltrim($type, '\\'));

        return $key === false ? Str::snake(class_basename($type)) : (string) $key;
    }

    private static function nextGroupPosition(): int
    {
        return ((int) AttributeGroup::query()->max('position')) + 1;
    }

    /**
     * Lunar v2 stores attribute / group names as plain strings. A locale-keyed
     * array (the v1 shape) is still accepted for definition-file compatibility:
     * the current locale wins, falling back to the first entry.
     *
     * @param string|array<string,string> $value
     */
    private static function displayName(string|array $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return (string) ($value[app()->getLocale()] ?? reset($value) ?: '');
    }
}
