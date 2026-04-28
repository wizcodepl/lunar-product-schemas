@php
    /** @var \WizcodePl\LunarProductSchemas\Reports\ProductTypeHealth|null $health */
    $report = app(\WizcodePl\LunarProductSchemas\Reports\SchemaHealthReport::class);
@endphp

@if ($health === null)
    <p class="text-sm text-gray-500 dark:text-gray-400">
        {{ __('lunar-product-schemas::filament.schema_health.no_data') }}
    </p>
@else
    {{-- Top stats row — three Filament-style stat boxes inside the slide-over --}}
    <div class="grid grid-cols-3 gap-3">
        <div class="rounded-lg border border-success-100 bg-success-50/50 p-3 text-center dark:border-success-500/20 dark:bg-success-500/5">
            <p class="text-xs font-medium uppercase tracking-wider text-success-700 dark:text-success-400">
                {{ __('lunar-product-schemas::filament.schema_health.stat_complete') }}
            </p>
            <p class="mt-1 text-2xl font-bold text-success-900 dark:text-success-300">{{ $health->complete }}</p>
        </div>
        <div class="rounded-lg border border-warning-100 bg-warning-50/50 p-3 text-center dark:border-warning-500/20 dark:bg-warning-500/5">
            <p class="text-xs font-medium uppercase tracking-wider text-warning-700 dark:text-warning-400">
                {{ __('lunar-product-schemas::filament.schema_health.stat_partial') }}
            </p>
            <p class="mt-1 text-2xl font-bold text-warning-900 dark:text-warning-300">{{ $health->partial }}</p>
        </div>
        <div class="rounded-lg border border-danger-100 bg-danger-50/50 p-3 text-center dark:border-danger-500/20 dark:bg-danger-500/5">
            <p class="text-xs font-medium uppercase tracking-wider text-danger-700 dark:text-danger-400">
                {{ __('lunar-product-schemas::filament.schema_health.stat_missing') }}
            </p>
            <p class="mt-1 text-2xl font-bold text-danger-900 dark:text-danger-300">{{ $health->missing }}</p>
        </div>
    </div>

    {{-- Progress bar --}}
    <div class="mt-5">
        <div class="mb-1 flex items-center justify-between text-xs">
            <span class="font-medium text-gray-600 dark:text-gray-400">
                {{ __('lunar-product-schemas::filament.schema_health.col_completeness') }}
            </span>
            <span class="font-mono tabular-nums text-gray-900 dark:text-white">
                {{ number_format($health->completePercentage(), 1) }}%
            </span>
        </div>
        <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
            <div class="h-full rounded-full bg-success-500" style="width: {{ $health->completePercentage() }}%"></div>
        </div>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            {{ $health->totalProducts }} {{ $health->totalProducts === 1 ? 'product' : 'products' }}
        </p>
    </div>

    {{-- Required fields --}}
    @if (! empty($health->requiredAttributeHandles))
        <div class="mt-6">
            <h4 class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                {{ __('lunar-product-schemas::filament.schema_health.required_fields') }}
                ({{ count($health->requiredAttributeHandles) }})
            </h4>
            <div class="mt-2 flex flex-wrap gap-1.5">
                @foreach ($health->requiredAttributeHandles as $required)
                    <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-1 font-mono text-xs text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                        {{ $required }}
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Missing breakdown — collapsible product lists per attribute --}}
    @if (! empty($health->missingByAttribute))
        <div class="mt-6">
            <h4 class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                {{ __('lunar-product-schemas::filament.schema_health.missing_breakdown') }}
            </h4>
            <div class="mt-2 space-y-2">
                @foreach ($health->missingByAttribute as $handle => $count)
                    @php $products = $report->productsMissing($health->productType->handle, $handle); @endphp
                    <details class="group rounded-lg border border-gray-200 bg-white open:shadow-sm dark:border-white/10 dark:bg-gray-900">
                        <summary class="flex cursor-pointer items-center justify-between gap-3 px-3 py-2.5 text-sm">
                            <span class="flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                                     class="h-4 w-4 text-gray-400 transition group-open:rotate-90 dark:text-gray-500">
                                    <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd" />
                                </svg>
                                <span class="truncate font-mono text-xs text-gray-700 dark:text-gray-300">{{ $handle }}</span>
                            </span>
                            <span class="inline-flex shrink-0 items-center rounded-full bg-danger-50 px-2 py-0.5 text-[10px] font-medium text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">
                                {{ $count }} {{ __('lunar-product-schemas::filament.schema_health.missing_label') }}
                            </span>
                        </summary>
                        <ul class="divide-y divide-gray-100 border-t border-gray-100 max-h-64 overflow-y-auto dark:divide-white/10 dark:border-white/10">
                            @foreach ($products as $product)
                                <li class="flex items-baseline gap-3 px-3 py-2 transition hover:bg-gray-50 dark:hover:bg-gray-800/30">
                                    <span class="shrink-0 font-mono text-[11px] text-gray-400 dark:text-gray-500">#{{ $product->id }}</span>
                                    <span class="min-w-0 flex-1 truncate text-sm text-gray-900 dark:text-white">
                                        {{ $product->translateAttribute('name') ?: __('lunar-product-schemas::filament.schema_health.unnamed_product') }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endforeach
            </div>
        </div>
    @else
        <div class="mt-6 rounded-md bg-success-50 px-3 py-2.5 text-center text-xs font-medium text-success-800 dark:bg-success-500/10 dark:text-success-300">
            {{ __('lunar-product-schemas::filament.schema_health.all_complete') }}
        </div>
    @endif
@endif
