<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas\Builders;

use Illuminate\Support\Collection;
use Lunar\Core\Models\Attribute;
use Lunar\Core\Models\Product;

class AttributeBuilder
{
    private Attribute $attribute;

    private string $modelType;

    public function __construct(string $handle, ?string $modelType = null)
    {
        $this->modelType = $modelType ?? Product::morphName();

        // Lunar v2: an attribute's model-type association lives in the
        // `attribute_models` pivot (model_type = morph name), not a column.
        $this->attribute = Attribute::query()
            ->where('handle', $handle)
            ->whereHas('models', fn ($query) => $query->where('model_type', $this->modelType))
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
     * Rename the attribute handle. In Lunar v2 `attribute_data` is stored
     * **id-keyed** (the cast maps handle <-> id), so renaming the handle does
     * NOT require rewriting stored product/variant data — it follows automatically.
     */
    public function rename(string $newHandle): self
    {
        if ($this->attribute->handle !== $newHandle) {
            $this->attribute->update(['handle' => $newHandle]);
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
}
