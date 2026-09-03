<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas\Builders;

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
            ->where('handle', ProductTypeBuilder::normalizeHandle($handle))
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

    /**
     * Set the display name. Lunar v2 stores attribute names as plain strings
     * (no per-locale translations).
     */
    public function name(string $name): self
    {
        $this->attribute->update(['name' => $name]);

        return $this;
    }

    /**
     * Rename the attribute handle. In Lunar v2 `attribute_data` is stored
     * **id-keyed** (the cast maps handle <-> id), so renaming the handle does
     * NOT require rewriting stored product/variant data — it follows automatically.
     */
    public function rename(string $newHandle): self
    {
        $newHandle = ProductTypeBuilder::normalizeHandle($newHandle);

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
