<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas\Builders;

use Illuminate\Support\Str;
use Lunar\FieldTypes\Text;
use Lunar\Models\Attribute;
use Lunar\Models\AttributeGroup;
use Lunar\Models\Product;
use Lunar\Models\ProductType;

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
     * Define (create or update) an attribute and attach it to this product type.
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
        $attributeGroup = AttributeGroup::firstOrCreate(
            [
                'handle' => $group,
                'attributable_type' => Product::morphName(),
            ],
            [
                'name' => self::localized($groupName ?? Str::headline($group)),
                'position' => self::nextGroupPosition(),
            ],
        );

        $existing = Attribute::query()
            ->where('handle', $handle)
            ->where('attribute_type', Product::morphName())
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
                'attribute_type' => Product::morphName(),
            ],
            $payload,
        );

        if (! $this->type->mappedAttributes()->where('attribute_id', $attribute->id)->exists()) {
            $this->type->mappedAttributes()->attach($attribute->id);
        }

        return $this;
    }

    /**
     * Detach an attribute from this product type and strip its values from this type's products.
     * Other product types still using the attribute (and their products) are untouched.
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
     * Detach all attributes whose handles are NOT in the provided list.
     * Useful to keep a product type's attribute set perfectly in sync with a migration's intent.
     */
    public function syncAttributes(array $keep): self
    {
        $keepIds = Attribute::query()
            ->whereIn('handle', $keep)
            ->where('attribute_type', Product::morphName())
            ->pluck('id');

        $this->type->mappedAttributes()->sync($keepIds->all());

        return $this;
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
