@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="KADPOLY" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8.5 items-center justify-center rounded-lg bg-gradient-to-br from-amber-400 via-amber-500 to-amber-600 text-slate-950 font-bold shadow-xs ring-1 ring-amber-400/30">
            <x-app-logo-icon class="size-5 fill-current text-slate-950" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="KADPOLY" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8.5 items-center justify-center rounded-lg bg-gradient-to-br from-amber-400 via-amber-500 to-amber-600 text-slate-950 font-bold shadow-xs ring-1 ring-amber-400/30">
            <x-app-logo-icon class="size-5 fill-current text-slate-950" />
        </x-slot>
    </flux:brand>
@endif
