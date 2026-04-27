<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas\Builders;

use Illuminate\Support\Str;
use Lunar\FieldTypes\Text;
use Lunar\Models\Attribute;
use Lunar\Models\AttributeGroup;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;

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

    /**
     * Define (create or update) a product-level attribute and attach it to this product type.
     * Values land in `Product.attribute_data` JSON.
     *
     * @param string|array<string,string>|null $name Localized name. String wraps in [locale => name] using app()->getLocale().
     * @param string|array<string,string>|null $groupName Same convention as $name.
     */
    public function attribute(
        string $handle,
        string|array|null $name = null,
        ?string $type = null,
        string $group = 'spec',
        string|array|null $groupName = null,
        ?bool $searchable = null,
        ?bool $filterable = null,
        ?bool $required = null,
    ): self {
        return $this->upsertAttribute(
            attributableType: Product::morphName(),
            handle: $handle,
            name: $name,
            type: $type,
            group: $group,
            groupName: $groupName,
            searchable: $searchable,
            filterable: $filterable,
            required: $required,
        );
    }

    /**
     * Define (create or update) a variant-level attribute and attach it to this product type.
     * Values land in `ProductVariant.attribute_data` JSON. Use this for per-SKU descriptive
     * data the customer doesn't pick (lead time, batch number, pantone code, etc.).
     *
     * For customer-pickable variant axes (Size, Color), use ProductOption — out of scope for this package.
     *
     * @param string|array<string,string>|null $name Localized name. String wraps in [locale => name] using app()->getLocale().
     * @param string|array<string,string>|null $groupName Same convention as $name.
     */
    public function variantAttribute(
        string $handle,
        string|array|null $name = null,
        ?string $type = null,
        string $group = 'variant_spec',
        string|array|null $groupName = null,
        ?bool $searchable = null,
        ?bool $filterable = null,
        ?bool $required = null,
    ): self {
        return $this->upsertAttribute(
            attributableType: ProductVariant::morphName(),
            handle: $handle,
            name: $name,
            type: $type,
            group: $group,
            groupName: $groupName,
            searchable: $searchable,
            filterable: $filterable,
            required: $required,
        );
    }

    /**
     * Detach a product-level attribute from this product type and strip its values from this
     * type's products. Other product types still using the attribute (and their products) are
     * untouched.
     */
    public function dropAttribute(string $handle): self
    {
        $attribute = Attribute::query()
            ->where('handle', $handle)
            ->where('attribute_type', Product::morphName())
            ->first();

        if (! $attribute) {
            return $this;
        }

        $this->type->mappedAttributes()->detach($attribute->id);

        Product::query()
            ->where('product_type_id', $this->type->id)
            ->chunkById(500, function ($products) use ($handle) {
                foreach ($products as $product) {
                    $data = $product->attribute_data;
                    if ($data?->has($handle)) {
                        $data->forget($handle);
                        $product->attribute_data = $data;
                        $product->saveQuietly();
                    }
                }
            });

        return $this;
    }

    /**
     * Detach a variant-level attribute from this product type and strip its values from
     * the variants of products of this type. Other product types still using the attribute
     * (and their variants) are untouched.
     */
    public function dropVariantAttribute(string $handle): self
    {
        $attribute = Attribute::query()
            ->where('handle', $handle)
            ->where('attribute_type', ProductVariant::morphName())
            ->first();

        if (! $attribute) {
            return $this;
        }

        $this->type->mappedAttributes()->detach($attribute->id);

        ProductVariant::query()
            ->whereHas('product', fn ($query) => $query->where('product_type_id', $this->type->id))
            ->chunkById(500, function ($variants) use ($handle) {
                foreach ($variants as $variant) {
                    $data = $variant->attribute_data;
                    if ($data?->has($handle)) {
                        $data->forget($handle);
                        $variant->attribute_data = $data;
                        $variant->saveQuietly();
                    }
                }
            });

        return $this;
    }

    /**
     * Detach all product-level attributes whose handles are NOT in the provided list.
     * Variant-level attributes attached to this type are untouched.
     */
    public function syncAttributes(array $keep): self
    {
        return $this->syncAttributesOfType(Product::morphName(), $keep);
    }

    /**
     * Detach all variant-level attributes whose handles are NOT in the provided list.
     * Product-level attributes attached to this type are untouched.
     */
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
        string $attributableType,
        string $handle,
        string|array|null $name,
        ?string $type,
        string $group,
        string|array|null $groupName,
        ?bool $searchable,
        ?bool $filterable,
        ?bool $required,
    ): self {
        // Lunar's `lunar_attribute_groups.handle` has a global UNIQUE constraint — it is NOT
        // scoped by `attributable_type`. So we look up by handle alone and reuse whatever
        // group already exists; `attributable_type` is only set on first create. Without this
        // reuse, defining a variant attribute in the same logical group as a product attribute
        // (e.g. both in 'general') would crash with a UniqueConstraintViolationException.
        $attributeGroup = AttributeGroup::firstOrCreate(
            ['handle' => $group],
            [
                'attributable_type' => $attributableType,
                'name' => self::localized($groupName ?? Str::headline($group)),
                'position' => self::nextGroupPosition(),
            ],
        );

        $existing = Attribute::query()
            ->where('handle', $handle)
            ->where('attribute_type', $attributableType)
            ->first();

        // Only include keys the caller explicitly passed; null means "leave as-is".
        $payload = array_filter(
            [
                'attribute_group_id' => $attributeGroup->id,
                'name' => $name !== null ? self::localized($name) : null,
                'type' => $type,
                'searchable' => $searchable,
                'filterable' => $filterable,
                'required' => $required,
            ],
            fn ($value) => $value !== null,
        );

        if ($existing === null) {
            // Defaults applied on first create only. `name` is required by the schema,
            // so derive a readable fallback from the handle when the caller omits it.
            $payload += [
                'name' => self::localized(Str::headline($handle)),
                'type' => Text::class,
                'searchable' => true,
                'filterable' => false,
                'required' => false,
                'system' => false,
                'section' => 'main',
                'position' => ((int) Attribute::query()
                    ->where('attribute_group_id', $attributeGroup->id)
                    ->max('position')) + 1,
                'configuration' => [],
                'description' => self::localized(''),
            ];
        }

        $attribute = Attribute::updateOrCreate(
            [
                'handle' => $handle,
                'attribute_type' => $attributableType,
            ],
            $payload,
        );

        if (! $this->type->mappedAttributes()->where('attribute_id', $attribute->id)->exists()) {
            $this->type->mappedAttributes()->attach($attribute->id);
        }

        return $this;
    }

    private function syncAttributesOfType(string $attributableType, array $keep): self
    {
        $keepIds = Attribute::query()
            ->whereIn('handle', $keep)
            ->where('attribute_type', $attributableType)
            ->pluck('id');

        $currentIdsForType = $this->type->mappedAttributes()
            ->where('attribute_type', $attributableType)
            ->pluck('attribute_id');

        $toDetach = $currentIdsForType->diff($keepIds);

        if ($toDetach->isNotEmpty()) {
            $this->type->mappedAttributes()->detach($toDetach->all());
        }

        // Attach any keep-list IDs that aren't yet attached (idempotent).
        $toAttach = $keepIds->diff($currentIdsForType);
        foreach ($toAttach as $id) {
            $this->type->mappedAttributes()->attach($id);
        }

        return $this;
    }

    private static function nextGroupPosition(): int
    {
        return ((int) AttributeGroup::query()->max('position')) + 1;
    }

    /**
     * @param string|array<string,string> $value
     * @return array<string,string>
     */
    private static function localized(string|array $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        return [app()->getLocale() => $value];
    }
}
