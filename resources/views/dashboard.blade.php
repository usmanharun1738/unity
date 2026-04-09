<x-layouts::app :title="__('Dashboard')">
    @php
        $displayName = str(auth()->user()->name)->before(' ');

        $current = max($monthComparison['current'], 1);
        $previous = max($monthComparison['previous'], 1);
        $monthDelta = round((($current - $previous) / $previous) * 100, 1);

        $lineValues = collect($monthlyEnrollmentCounts)->pluck('value');
        $lineMax = max($lineValues->max(), 1);
        $linePoints = collect($monthlyEnrollmentCounts)
            ->values()
            ->map(function (array $point, int $index) use ($lineMax): string {
                $x = (int) round(($index / 5) * 100);
                $y = (int) round(100 - (($point['value'] / $lineMax) * 100));

                return $x.','.$y;
            })
            ->implode(' ');

        $totalDistribution = max(collect($departmentDistribution)->sum('count'), 1);
        $palette = ['#6D28D9', '#7C3AED', '#8B5CF6', '#A78BFA', '#C4B5FD'];
        $offset = 0;
        $segments = [];

        foreach ($departmentDistribution as $index => $item) {
            $share = round(($item['count'] / $totalDistribution) * 100, 2);
            $end = min(100, $offset + $share);
            $segments[] = $palette[$index % count($palette)].' '.$offset.'% '.$end.'%';
            $offset = $end;
        }

        if ($offset < 100) {
            $segments[] = '#E4E4E7 '.$offset.'% 100%';
        }

        $chartGradient = 'conic-gradient('.implode(', ', $segments).')';
    @endphp

    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">{{ __('Welcome back, :name', ['name' => $displayName]) }}</h1>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Track enrollment activity, course load, and academic operations.') }}</p>
                </div>

                <div class="flex items-center gap-2">
                    <flux:button variant="filled" icon="arrow-down-tray">{{ __('Import') }}</flux:button>
                    @if (auth()->user()->hasAnyRole(['admin', 'department-staff']))
                        <flux:button variant="primary" icon="plus" :href="route('courses.index')" wire:navigate>{{ __('Add') }}</flux:button>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            @if (auth()->user()->hasAnyRole(['admin', 'department-staff']))
                <a href="{{ route('subjects.index') }}" wire:navigate class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm transition hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Add Subject') }}</div>
                    <div class="mt-1 text-sm text-zinc-500">{{ __('Create or import a new subject offering.') }}</div>
                </a>
                <a href="{{ route('courses.index') }}" wire:navigate class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm transition hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Add Class') }}</div>
                    <div class="mt-1 text-sm text-zinc-500">{{ __('Open class management and publish schedules.') }}</div>
                </a>
            @else
                <a href="{{ route('enrollments.index') }}" wire:navigate class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm transition hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('View Enrollments') }}</div>
                    <div class="mt-1 text-sm text-zinc-500">{{ __('Review your current class enrollments.') }}</div>
                </a>
                <a href="{{ route('enrollments.index') }}" wire:navigate class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm transition hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Browse Available Classes') }}</div>
                    <div class="mt-1 text-sm text-zinc-500">{{ __('Use enrollment codes to join open classes.') }}</div>
                </a>
            @endif
            <a href="{{ route('enrollments.index') }}" wire:navigate class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm transition hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900">
                <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Join Class') }}</div>
                <div class="mt-1 text-sm text-zinc-500">{{ __('Use an enrollment code to join a class.') }}</div>
            </a>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($kpiCards as $card)
                <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="text-sm font-medium text-zinc-500">{{ __($card['label']) }}</div>
                    <div class="mt-2 text-3xl font-semibold text-zinc-900 dark:text-zinc-100">{{ number_format($card['value']) }}</div>
                </div>
            @endforeach
        </div>

        <div class="grid gap-4 xl:grid-cols-5">
            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 xl:col-span-2">
                <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Enrollment Breakdown by Department') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ __('Distribution of enrolled students across active departments.') }}</p>

                <div class="mt-5 flex items-center gap-6">
                    <div class="h-40 w-40 rounded-full" style="background: {{ $chartGradient }};">
                        <div class="m-6 flex h-28 w-28 items-center justify-center rounded-full bg-white text-sm font-semibold text-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">
                            {{ number_format($totalDistribution) }}
                        </div>
                    </div>

                    <div class="space-y-3">
                        @foreach ($departmentDistribution as $index => $item)
                            <div class="flex items-center gap-2 text-sm">
                                <span class="inline-block h-2.5 w-2.5 rounded-full" style="background-color: {{ $palette[$index % count($palette)] }}"></span>
                                <span class="text-zinc-700 dark:text-zinc-200">{{ $item['name'] }}</span>
                                <span class="text-zinc-400">{{ number_format($item['count']) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 xl:col-span-3">
                <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Enrollment Trend (Last 6 Months)') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">
                    {{ __('Current month: :current | Previous month: :previous', ['current' => number_format($monthComparison['current']), 'previous' => number_format($monthComparison['previous'])]) }}
                    <span class="ml-2 font-medium {{ $monthDelta >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $monthDelta >= 0 ? '+' : '' }}{{ $monthDelta }}%</span>
                </p>

                <div class="mt-6 rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/60">
                    <svg viewBox="0 0 100 100" class="h-44 w-full" preserveAspectRatio="none" role="img" aria-label="Enrollment trend chart">
                        <polyline points="0,100 100,100" fill="none" stroke="#E4E4E7" stroke-width="0.6" />
                        <polyline points="{{ $linePoints }}" fill="none" stroke="#7C3AED" stroke-width="1.4" />
                    </svg>

                    <div class="mt-3 grid grid-cols-6 gap-2 text-center text-xs text-zinc-500">
                        @foreach ($monthlyEnrollmentCounts as $point)
                            <div>{{ $point['label'] }}</div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts::app>
