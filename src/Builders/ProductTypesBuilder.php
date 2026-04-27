<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas\Builders;

/**
 * Apply the same attribute schema to several product types in one chain.
 *
 *   ProductSchema::productTypes([
 *       't-shirts' => 'T-shirts',
 *       'shoes'    => 'Shoes',
 *       'bags'     => 'Bags',
 *   ])
 *       ->attribute('name', name: ['en' => 'Name'], type: TranslatedText::class, required: true)
 *       ->attribute('color', name: ['en' => 'Color'], filterable: true);
 *
 * Each method call fans out to every underlying ProductTypeBuilder. Per-type
 * differences are handled with regular ProductSchema::productType(...) calls
 * after (or before) the shared block.
 */
class ProductTypesBuilder
{
    /** @var array<int, ProductTypeBuilder> */
    private array $builders;

    /**
     * @param array<string, string|null>|array<int, string> $types
     *                                                             Either a map of handle => display name, or a flat list of handles
     *                                                             (names auto-derived from handle).
     */
    public function __construct(array $types)
    {
        $this->builders = [];
        foreach (self::normalize($types) as $handle => $name) {
            $this->builders[] = new ProductTypeBuilder($handle, $name);
        }
    }

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
        foreach ($this->builders as $builder) {
            $builder->attribute(
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

        return $this;
    }

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
        foreach ($this->builders as $builder) {
            $builder->variantAttribute(
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

        return $this;
    }

    public function dropAttribute(string $handle): self
    {
        foreach ($this->builders as $builder) {
            $builder->dropAttribute($handle);
        }

        return $this;
    }

    public function dropVariantAttribute(string $handle): self
    {
        foreach ($this->builders as $builder) {
            $builder->dropVariantAttribute($handle);
        }

        return $this;
    }

    public function syncAttributes(array $keep): self
    {
        foreach ($this->builders as $builder) {
            $builder->syncAttributes($keep);
        }

        return $this;
    }

    public function syncVariantAttributes(array $keep): self
    {
        foreach ($this->builders as $builder) {
            $builder->syncVariantAttributes($keep);
        }

        return $this;
    }

    /**
     * Restrict subsequent calls to a subset of the originally-defined types.
     */
    public function only(string ...$handles): self
    {
        $clone = clone $this;
        $clone->builders = array_values(array_filter(
            $this->builders,
            fn (ProductTypeBuilder $b) => in_array($b->model()->handle, $handles, true),
        ));

        return $clone;
    }

    /**
     * @param array<string, string|null>|array<int, string> $types
     * @return array<string, string|null>
     */
    private static function normalize(array $types): array
    {
        $out = [];
        foreach ($types as $key => $value) {
            if (is_int($key)) {
                $out[(string) $value] = null;
            } else {
                $out[(string) $key] = $value === null ? null : (string) $value;
            }
        }

        return $out;
    }
}
