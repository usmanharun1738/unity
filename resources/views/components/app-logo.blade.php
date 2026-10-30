@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="UNITY ACADEMIC" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-lg bg-amber-500 text-slate-950 font-bold shadow-xs">
            <x-app-logo-icon class="size-4.5 fill-current text-slate-950" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="UNITY ACADEMIC" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-lg bg-amber-500 text-slate-950 font-bold shadow-xs">
            <x-app-logo-icon class="size-4.5 fill-current text-slate-950" />
        </x-slot>
    </flux:brand>
@endif
