<?php

declare(strict_types=1);

namespace WizcodePl\LunarProductSchemas\Health;

use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Spatie health check that flags ProductTypes whose products are missing
 * `required` attribute data. Drop into `Health::checks([...])` alongside
 * the other wizcodepl checks; thresholds are tunable per environment.
 */
class ProductSchemaHealthCheck extends Check
{
    protected float $minCompletePercentage = 95.0;

    protected int $warningCompletePercentage = 70;

    public function minCompletePercentage(float $percentage): self
    {
        $this->minCompletePercentage = $percentage;

        return $this;
    }

    public function warningCompletePercentage(int $percentage): self
    {
        $this->warningCompletePercentage = $percentage;

        return $this;
    }

    public function run(): Result
    {
        $totals = ['total' => 0, 'complete' => 0, 'partial' => 0, 'missing' => 0];
        $perType = [];

        $types = ProductType::query()
            ->with(['mappedAttributes' => function ($q) {
                $q->where('attribute_type', Product::morphName())
                    ->where('required', true);
            }])
            ->orderBy('id')
            ->get();

        foreach ($types as $type) {
            $stats = $this->statsFor($type);

            $totals['total'] += $stats['total'];
            $totals['complete'] += $stats['complete'];
            $totals['partial'] += $stats['partial'];
            $totals['missing'] += $stats['missing'];

            $perType[$type->handle] = $stats;
        }

        $percentage = $totals['total'] > 0
            ? ($totals['complete'] / $totals['total']) * 100
            : 100.0;

        $result = Result::make()
            ->shortSummary(sprintf(
                '%s%% complete (%d/%d)',
                number_format($percentage, 0),
                $totals['complete'],
                $totals['total']
            ))
            ->meta([
                'complete_percentage' => round($percentage, 2),
                'total_products' => $totals['total'],
                'complete' => $totals['complete'],
                'partial' => $totals['partial'],
                'missing' => $totals['missing'],
                'by_product_type' => $perType,
            ]);

        if ($percentage < $this->minCompletePercentage) {
            return $result->failed(sprintf(
                'Schema completeness %.0f%% below threshold %.0f%%',
                $percentage,
                $this->minCompletePercentage
            ));
        }

        if ($percentage < $this->warningCompletePercentage) {
            return $result->warning(sprintf(
                'Schema completeness %.0f%% below warning threshold %d%%',
                $percentage,
                $this->warningCompletePercentage
            ));
        }

        return $result->ok();
    }

    /**
     * @return array{total:int, complete:int, partial:int, missing:int, required:list<string>}
     */
    private function statsFor(ProductType $type): array
    {
        $requiredHandles = $type->mappedAttributes->pluck('handle')->all();

        $products = Product::query()
            ->where('product_type_id', $type->id)
            ->get();

        $complete = 0;
        $partial = 0;
        $missing = 0;

        foreach ($products as $product) {
            if ($requiredHandles === []) {
                $complete++;

                continue;
            }

            $filled = 0;
            foreach ($requiredHandles as $handle) {
                if ($this->hasValue($product, $handle)) {
                    $filled++;
                }
            }

            if ($filled === count($requiredHandles)) {
                $complete++;
            } elseif ($filled === 0) {
                $missing++;
            } else {
                $partial++;
            }
        }

        return [
            'total' => $products->count(),
            'complete' => $complete,
            'partial' => $partial,
            'missing' => $missing,
            'required' => $requiredHandles,
        ];
    }

    private function hasValue(Product $product, string $handle): bool
    {
        $data = $product->attribute_data;

        if ($data === null || ! $data->has($handle)) {
            return false;
        }

        $value = $data->get($handle);

        if (is_object($value) && method_exists($value, 'getValue')) {
            $value = $value->getValue();
        }

        return $value !== null && $value !== '' && $value !== [];
    }
}
