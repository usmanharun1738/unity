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
                $y = (int) round(100 - (($point['value'] / $lineMax) * 80) - 10);

                return $x.','.$y;
            })
            ->implode(' ');

        // Area polygon closed at the bottom
        $firstX = 0;
        $lastX = 100;
        $areaPoints = "0,100 {$linePoints} 100,100";

        $totalDistribution = max(collect($departmentDistribution)->sum('count'), 1);
        // Calibrated Oxford Slate & Academic Amber chart palette
        $palette = ['#0f172a', '#334155', '#d97706', '#f59e0b', '#64748b', '#fbbf24'];
        $offset = 0;
        $segments = [];

        foreach ($departmentDistribution as $index => $item) {
            $share = round(($item['count'] / $totalDistribution) * 100, 2);
            $end = min(100, $offset + $share);
            $segments[] = $palette[$index % count($palette)].' '.$offset.'% '.$end.'%';
            $offset = $end;
        }

        if ($offset < 100) {
            $segments[] = '#cbd5e1 '.$offset.'% 100%';
        }

        $chartGradient = 'conic-gradient('.implode(', ', $segments).')';

        $primaryRole = auth()->user()->roles->first()?->name ?? 'student';
        $roleTitle = match ($primaryRole) {
            'admin' => 'Administrator',
            'department-staff' => 'Department Staff',
            'faculty' => 'Faculty Instructor',
            default => 'Student Scholar',
        };
    @endphp

    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <!-- Academic Command Center Banner -->
        <div class="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900/60 p-6 shadow-xs backdrop-blur-xs">
            <div class="absolute -right-16 -top-16 size-64 rounded-full bg-amber-500/5 blur-3xl pointer-events-none"></div>
            <div class="flex flex-wrap items-center justify-between gap-4 relative z-10">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-500/10 px-2.5 py-0.5 text-[11px] font-semibold tracking-wide text-amber-700 dark:text-amber-400 border border-amber-500/20">
                            <span class="size-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                            {{ $roleTitle }}
                        </span>
                        <span class="text-xs text-slate-400">|</span>
                        <span class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ now()->format('l, F j, Y') }}</span>
                    </div>
                    <h1 class="text-2xl lg:text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        {{ __('Welcome back, :name', ['name' => $displayName]) }}
                    </h1>
                    <p class="text-sm text-slate-600 dark:text-slate-400 max-w-2xl">
                        {{ __('Unified operational oversight of academic programs, enrollment velocity, curriculum catalogs, and institutional performance.') }}
                    </p>
                </div>

                <div class="flex items-center gap-2.5">
                    @if (auth()->user()->hasAnyRole(['admin', 'department-staff']))
                        <flux:button variant="filled" icon="book-open" :href="route('subjects.index')" wire:navigate class="text-xs font-medium">
                            {{ __('Subject Catalog') }}
                        </flux:button>
                        <flux:button variant="primary" icon="plus" :href="route('courses.index')" wire:navigate class="text-xs font-medium shadow-xs">
                            {{ __('Manage Sections') }}
                        </flux:button>
                    @elseif (auth()->user()->hasRole('faculty'))
                        <flux:button variant="filled" icon="users" :href="route('students.my-classes')" wire:navigate class="text-xs font-medium">
                            {{ __('Class Roster') }}
                        </flux:button>
                        <flux:button variant="primary" icon="academic-cap" :href="route('courses.browse')" wire:navigate class="text-xs font-medium shadow-xs">
                            {{ __('My Classes') }}
                        </flux:button>
                    @else
                        <flux:button variant="filled" icon="academic-cap" :href="route('courses.browse')" wire:navigate class="text-xs font-medium">
                            {{ __('Browse Catalog') }}
                        </flux:button>
                        <flux:button variant="primary" icon="ticket" :href="route('enrollments.index')" wire:navigate class="text-xs font-medium shadow-xs">
                            {{ __('Join Class') }}
                        </flux:button>
                    @endif
                </div>
            </div>
        </div>

        <!-- High-Density KPI Metric Ribbon -->
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach ($kpiCards as $index => $card)
                @php
                    $kpiMeta = match ($card['label']) {
                        'Total students' => ['sub' => 'Enrolled scholars', 'icon' => 'academic-cap', 'accent' => 'border-amber-500/30'],
                        'Faculty' => ['sub' => 'Instructional staff', 'icon' => 'user-group', 'accent' => 'border-slate-500/30'],
                        'Classes' => ['sub' => 'Active course sections', 'icon' => 'book-open', 'accent' => 'border-slate-500/30'],
                        'Subjects' => ['sub' => 'Curriculum catalog', 'icon' => 'bookmark-square', 'accent' => 'border-slate-500/30'],
                        default => ['sub' => 'Academic records', 'icon' => 'chart-bar', 'accent' => 'border-slate-500/30'],
                    };
                @endphp
                <div class="group relative overflow-hidden rounded-xl border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900/60 p-5 shadow-xs transition hover:border-slate-300 dark:hover:border-slate-700">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            {{ __($card['label']) }}
                        </span>
                        <div class="flex size-7 items-center justify-center rounded-lg bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                            <flux:icon :icon="$kpiMeta['icon']" class="size-4" />
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-2">
                        <span class="font-mono text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100 tabular-nums">
                            {{ number_format($card['value']) }}
                        </span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                        <span>{{ __($kpiMeta['sub']) }}</span>
                        <span class="font-mono text-[10px] text-slate-400">#{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Role-Based Academic Quick Actions -->
        <div class="grid gap-4 md:grid-cols-3">
            @if (auth()->user()->hasAnyRole(['admin', 'department-staff']))
                <a href="{{ route('subjects.index') }}" wire:navigate class="group rounded-xl border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900/60 p-4.5 shadow-xs transition hover:border-amber-500/50 hover:shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 group-hover:bg-amber-500 group-hover:text-slate-950 transition-colors">
                            <flux:icon icon="plus-circle" class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('Add Subject') }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ __('Create or import a new subject offering.') }}</div>
                        </div>
                    </div>
                </a>

                <a href="{{ route('courses.index') }}" wire:navigate class="group rounded-xl border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900/60 p-4.5 shadow-xs transition hover:border-amber-500/50 hover:shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 group-hover:bg-slate-900 group-hover:text-white dark:group-hover:bg-slate-700 transition-colors">
                            <flux:icon icon="academic-cap" class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('Add Class') }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ __('Open class management and publish schedules.') }}</div>
                        </div>
                    </div>
                </a>

                <a href="{{ route('enrollments.index') }}" wire:navigate class="group rounded-xl border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900/60 p-4.5 shadow-xs transition hover:border-amber-500/50 hover:shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 group-hover:bg-slate-900 group-hover:text-white dark:group-hover:bg-slate-700 transition-colors">
                            <flux:icon icon="ticket" class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('Join Class') }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ __('Use an enrollment code to join a class.') }}</div>
                        </div>
                    </div>
                </a>
            @else
                <a href="{{ route('enrollments.index') }}" wire:navigate class="group rounded-xl border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900/60 p-4.5 shadow-xs transition hover:border-amber-500/50 hover:shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 group-hover:bg-slate-900 group-hover:text-white dark:group-hover:bg-slate-700 transition-colors">
                            <flux:icon icon="clipboard-document-list" class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('View Enrollments') }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ __('Review your current class enrollments.') }}</div>
                        </div>
                    </div>
                </a>

                <a href="{{ route('courses.browse') }}" wire:navigate class="group rounded-xl border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900/60 p-4.5 shadow-xs transition hover:border-amber-500/50 hover:shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 group-hover:bg-slate-900 group-hover:text-white dark:group-hover:bg-slate-700 transition-colors">
                            <flux:icon icon="magnifying-glass" class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('Browse Available Classes') }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ __('Explore course catalog and syllabus details.') }}</div>
                        </div>
                    </div>
                </a>

                <a href="{{ route('enrollments.index') }}" wire:navigate class="group rounded-xl border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900/60 p-4.5 shadow-xs transition hover:border-amber-500/50 hover:shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 group-hover:bg-amber-500 group-hover:text-slate-950 transition-colors">
                            <flux:icon icon="ticket" class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('Join Class') }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ __('Use an enrollment code to join a class.') }}</div>
                        </div>
                    </div>
                </a>
            @endif
        </div>

        <!-- Academic Intelligence Panels -->
        <div class="grid gap-6 xl:grid-cols-5">
            <!-- Department Distribution Donut Card -->
            <div class="rounded-2xl border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900/60 p-6 shadow-xs xl:col-span-2">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold tracking-tight text-slate-900 dark:text-slate-100">
                            {{ __('Enrollment Breakdown by Department') }}
                        </h2>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            {{ __('Distribution of enrolled students across active departments.') }}
                        </p>
                    </div>
                    <span class="rounded-md bg-slate-100 dark:bg-slate-800 px-2 py-0.5 font-mono text-xs font-semibold text-slate-600 dark:text-slate-300">
                        Top 5
                    </span>
                </div>

                <div class="mt-6 flex flex-col sm:flex-row items-center gap-6">
                    <div class="relative shrink-0 flex items-center justify-center">
                        <div class="size-40 rounded-full shadow-inner" style="background: {{ $chartGradient }};">
                            <div class="m-6 flex size-28 items-center justify-center rounded-full bg-white dark:bg-slate-900 shadow-sm flex-col">
                                <span class="font-mono text-xl font-bold tracking-tight text-slate-900 dark:text-slate-100 tabular-nums">
                                    {{ number_format($totalDistribution) }}
                                </span>
                                <span class="text-[10px] uppercase tracking-wider text-slate-400 font-medium">Students</span>
                            </div>
                        </div>
                    </div>

                    <div class="w-full space-y-2.5">
                        @forelse ($departmentDistribution as $index => $item)
                            @php
                                $percent = round(($item['count'] / $totalDistribution) * 100, 1);
                            @endphp
                            <div class="flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $palette[$index % count($palette)] }}"></span>
                                    <span class="truncate font-medium text-slate-700 dark:text-slate-300">{{ $item['name'] }}</span>
                                </div>
                                <div class="flex items-center gap-2 pl-2 shrink-0">
                                    <span class="font-mono text-slate-500 dark:text-slate-400 tabular-nums">{{ number_format($item['count']) }}</span>
                                    <span class="font-mono text-[11px] text-slate-400 w-10 text-right tabular-nums">{{ $percent }}%</span>
                                </div>
                            </div>
                        @empty
                            <div class="text-xs text-slate-400 italic py-4 text-center">
                                {{ __('No department enrollment data available.') }}
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Enrollment Trend & Velocity Chart -->
            <div class="rounded-2xl border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900/60 p-6 shadow-xs xl:col-span-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="text-base font-bold tracking-tight text-slate-900 dark:text-slate-100">
                            {{ __('Enrollment Trend (Last 6 Months)') }}
                        </h2>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            {{ __('Current month: :current | Previous month: :previous', [
                                'current' => number_format($monthComparison['current']),
                                'previous' => number_format($monthComparison['previous']),
                            ]) }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1 rounded-md px-2 py-1 font-mono text-xs font-bold {{ $monthDelta >= 0 ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-700 dark:text-rose-400 border border-rose-500/20' }}">
                            <flux:icon :icon="$monthDelta >= 0 ? 'arrow-trending-up' : 'arrow-trending-down'" class="size-3.5" />
                            <span>{{ $monthDelta >= 0 ? '+' : '' }}{{ $monthDelta }}%</span>
                            <span class="text-[10px] font-normal text-slate-400 ml-0.5 uppercase tracking-wider">MoM</span>
                        </span>
                    </div>
                </div>

                <div class="mt-5 rounded-xl border border-slate-200/80 bg-slate-50/70 p-4 dark:border-slate-800/80 dark:bg-slate-950/40">
                    <svg viewBox="0 0 100 100" class="h-44 w-full overflow-visible" preserveAspectRatio="none" role="img" aria-label="Enrollment trend chart">
                        <defs>
                            <linearGradient id="academicTrendGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                                <stop offset="0%" stop-color="#d97706" stop-opacity="0.25" />
                                <stop offset="100%" stop-color="#d97706" stop-opacity="0.0" />
                            </linearGradient>
                        </defs>
                        <!-- Grid Lines -->
                        <line x1="0" y1="20" x2="100" y2="20" stroke="currentColor" class="text-slate-200 dark:text-slate-800/60" stroke-width="0.5" stroke-dasharray="2,2" />
                        <line x1="0" y1="50" x2="100" y2="50" stroke="currentColor" class="text-slate-200 dark:text-slate-800/60" stroke-width="0.5" stroke-dasharray="2,2" />
                        <line x1="0" y1="80" x2="100" y2="80" stroke="currentColor" class="text-slate-200 dark:text-slate-800/60" stroke-width="0.5" stroke-dasharray="2,2" />
                        <line x1="0" y1="100" x2="100" y2="100" stroke="currentColor" class="text-slate-300 dark:text-slate-700" stroke-width="0.75" />

                        <!-- Area Fill -->
                        <polygon points="{{ $areaPoints }}" fill="url(#academicTrendGrad)" />

                        <!-- Trend Line -->
                        <polyline points="{{ $linePoints }}" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>

                    <div class="mt-4 grid grid-cols-6 gap-2 text-center">
                        @foreach ($monthlyEnrollmentCounts as $point)
                            <div class="space-y-0.5">
                                <div class="font-mono text-xs font-semibold text-slate-800 dark:text-slate-200 tabular-nums">
                                    {{ number_format($point['value']) }}
                                </div>
                                <div class="text-[11px] font-medium text-slate-400 uppercase tracking-wider">
                                    {{ $point['label'] }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts::app>
