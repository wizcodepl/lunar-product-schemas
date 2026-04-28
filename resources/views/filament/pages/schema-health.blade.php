<x-filament-panels::page>
    @php
        /** @var array<int, \WizcodePl\LunarProductSchemas\Reports\ProductTypeHealth> $stats */
        $stats = $this->getStats();
        $drillDown = $this->getDrillDownProducts();
    @endphp

    @if (empty($stats))
        <div class="rounded-lg bg-gray-50 p-6 text-center text-sm text-gray-500 dark:bg-gray-800 dark:text-gray-400">
            No product types yet. Declare some via <code>product-schema:apply</code> and they'll appear here.
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            @foreach ($stats as $row)
                <div class="rounded-xl bg-white p-5 shadow ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                            {{ $row->productType->name }}
                        </h3>
                        <span class="text-xs font-mono text-gray-500 dark:text-gray-400">
                            {{ $row->productType->handle }}
                        </span>
                    </div>

                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ $row->totalProducts }} {{ $row->totalProducts === 1 ? 'product' : 'products' }}
                        @if (! empty($row->requiredAttributeHandles))
                            · required: <span class="font-mono">{{ implode(', ', $row->requiredAttributeHandles) }}</span>
                        @else
                            · no required attributes declared
                        @endif
                    </p>

                    <div class="mt-4">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-300">Complete</span>
                            <span class="font-semibold text-gray-950 dark:text-white">
                                {{ $row->complete }} / {{ $row->totalProducts }}
                            </span>
                        </div>
                        <div class="mt-1 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                            <div class="h-full bg-success-500" style="width: {{ $row->completePercentage() }}%"></div>
                        </div>
                        <div class="mt-1 text-right text-xs text-gray-500 dark:text-gray-400">
                            {{ number_format($row->completePercentage(), 1) }}%
                        </div>
                    </div>

                    <dl class="mt-4 grid grid-cols-3 gap-2 text-center text-xs">
                        <div class="rounded-md bg-success-50 p-2 dark:bg-success-500/10">
                            <dt class="font-medium text-success-700 dark:text-success-400">Complete</dt>
                            <dd class="mt-1 text-base font-semibold text-success-800 dark:text-success-300">
                                {{ $row->complete }}
                            </dd>
                        </div>
                        <div class="rounded-md bg-warning-50 p-2 dark:bg-warning-500/10">
                            <dt class="font-medium text-warning-700 dark:text-warning-400">Partial</dt>
                            <dd class="mt-1 text-base font-semibold text-warning-800 dark:text-warning-300">
                                {{ $row->partial }}
                            </dd>
                        </div>
                        <div class="rounded-md bg-danger-50 p-2 dark:bg-danger-500/10">
                            <dt class="font-medium text-danger-700 dark:text-danger-400">Missing</dt>
                            <dd class="mt-1 text-base font-semibold text-danger-800 dark:text-danger-300">
                                {{ $row->missing }}
                            </dd>
                        </div>
                    </dl>

                    @if (! empty($row->missingByAttribute))
                        <div class="mt-4 border-t border-gray-100 pt-3 dark:border-white/10">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Missing field breakdown
                            </p>
                            <ul class="mt-2 space-y-1 text-sm">
                                @foreach ($row->missingByAttribute as $handle => $count)
                                    <li class="flex items-center justify-between">
                                        <span class="font-mono text-gray-700 dark:text-gray-300">{{ $handle }}</span>
                                        <button
                                            type="button"
                                            wire:click="showMissing('{{ $row->productType->handle }}', '{{ $handle }}')"
                                            class="text-xs text-primary-600 hover:text-primary-700 dark:text-primary-400"
                                        >
                                            {{ $count }} missing →
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        @if ($drillDown !== null)
            <div class="mt-8 rounded-xl bg-white shadow ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center justify-between border-b border-gray-100 p-5 dark:border-white/10">
                    <div>
                        <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                            {{ $drillDown->count() }} product{{ $drillDown->count() === 1 ? '' : 's' }}
                            missing
                            <span class="font-mono">{{ $this->drillDownAttribute }}</span>
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            in product type <span class="font-mono">{{ $this->drillDownType }}</span>
                        </p>
                    </div>
                    <button
                        type="button"
                        wire:click="clearDrillDown"
                        class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                    >
                        Close
                    </button>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-5 py-2">ID</th>
                            <th class="px-5 py-2">Product</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($drillDown as $product)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-5 py-2 font-mono text-xs text-gray-500 dark:text-gray-400">
                                    {{ $product->id }}
                                </td>
                                <td class="px-5 py-2 text-gray-950 dark:text-white">
                                    {{ $product->translateAttribute('name') ?: '(no name)' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</x-filament-panels::page>
