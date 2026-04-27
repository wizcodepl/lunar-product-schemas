<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas\Builders;

use Illuminate\Support\Collection;
use Lunar\Models\Attribute;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

class AttributeBuilder
{
    private Attribute $attribute;

    private string $attributableType;

    public function __construct(string $handle, ?string $attributableType = null)
    {
        $this->attributableType = $attributableType ?? Product::morphName();

        $this->attribute = Attribute::query()
            ->where('handle', $handle)
            ->where('attribute_type', $this->attributableType)
            ->firstOrFail();
    }

    public function filterable(bool $value = true): self
    {
        return $this->setFlag('filterable', $value);
    }

    public function searchable(bool $value = true): self
    {
        return $this->setFlag('searchable', $value);
    }

    public function required(bool $value = true): self
    {
        return $this->setFlag('required', $value);
    }

    /**
     * Update the translated name of the attribute.
     */
    public function name(string $name, string $locale = 'en'): self
    {
        $current = $this->attribute->name;
        if ($current instanceof Collection) {
            $current = $current->all();
        }
        $current = is_array($current) ? $current : [];
        $current[$locale] = $name;

        $this->attribute->update(['name' => $current]);

        return $this;
    }

    /**
     * Rename the attribute handle and migrate any values stored under the old key
     * inside the appropriate `attribute_data` JSON layer (Product for product-level,
     * ProductVariant for variant-level).
     */
    public function rename(string $newHandle): self
    {
        $oldHandle = $this->attribute->handle;
        if ($oldHandle === $newHandle) {
            return $this;
        }

        $this->attribute->update(['handle' => $newHandle]);

        if ($this->attributableType === ProductVariant::morphName()) {
            $this->renameKeysInVariants($oldHandle, $newHandle);
        } else {
            $this->renameKeysInProducts($oldHandle, $newHandle);
        }

        return $this;
    }

    public function model(): Attribute
    {
        return $this->attribute->refresh();
    }

    private function setFlag(string $column, bool $value): self
    {
        $this->attribute->update([$column => $value]);

        return $this;
    }

    private function renameKeysInProducts(string $oldHandle, string $newHandle): void
    {
        Product::query()->chunkById(500, function ($products) use ($oldHandle, $newHandle) {
            foreach ($products as $product) {
                $data = $product->attribute_data;
                if ($data?->has($oldHandle)) {
                    $value = $data->get($oldHandle);
                    $data->forget($oldHandle);
                    $data->put($newHandle, $value);
                    $product->attribute_data = $data;
                    $product->saveQuietly();
                }
            }
        });
    }

    private function renameKeysInVariants(string $oldHandle, string $newHandle): void
    {
        ProductVariant::query()->chunkById(500, function ($variants) use ($oldHandle, $newHandle) {
            foreach ($variants as $variant) {
                $data = $variant->attribute_data;
                if ($data?->has($oldHandle)) {
                    $value = $data->get($oldHandle);
                    $data->forget($oldHandle);
                    $data->put($newHandle, $value);
                    $variant->attribute_data = $data;
                    $variant->saveQuietly();
                }
            }
        });
    }
}
