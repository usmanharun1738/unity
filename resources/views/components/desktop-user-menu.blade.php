@php
    $primaryRole = auth()->user()->roles->first()?->name ?? 'student';
    $roleDisplay = strtoupper(str_replace('-', ' ', $primaryRole));
@endphp

<flux:dropdown position="bottom" align="start">
    <flux:sidebar.profile
        :name="auth()->user()->name"
        :initials="auth()->user()->initials()"
        icon:trailing="chevrons-up-down"
        data-test="sidebar-menu-button"
        class="border border-slate-200/60 dark:border-slate-800/80 rounded-xl bg-white/50 dark:bg-slate-900/40 hover:bg-slate-100/70 dark:hover:bg-slate-800/60 transition-colors"
    />

    <flux:menu class="w-64 p-1.5 shadow-xl border border-slate-200/80 dark:border-slate-800">
        <div class="flex items-center gap-3 px-2 py-2 text-start">
            <flux:avatar
                :name="auth()->user()->name"
                :initials="auth()->user()->initials()"
                class="size-9 bg-slate-900 text-white dark:bg-amber-500 dark:text-slate-950 font-semibold"
            />
            <div class="grid flex-1 text-start leading-tight min-w-0">
                <div class="flex items-center gap-1.5">
                    <flux:heading class="truncate text-sm font-semibold">{{ auth()->user()->name }}</flux:heading>
                </div>
                <flux:text class="truncate text-xs text-slate-500">{{ auth()->user()->email }}</flux:text>
                <div class="mt-1">
                    <span class="inline-flex items-center rounded-xs bg-slate-100 dark:bg-slate-800 px-1.5 py-0.2 font-mono text-[9px] font-semibold tracking-wider text-slate-600 dark:text-slate-300 uppercase">
                        {{ $roleDisplay }}
                    </span>
                </div>
            </div>
        </div>

        <flux:menu.separator />

        <flux:menu.radio.group>
            <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate class="text-xs font-medium">
                {{ __('Account Settings') }}
            </flux:menu.item>
            <flux:menu.item :href="route('security.edit')" icon="shield-check" wire:navigate class="text-xs font-medium">
                {{ __('Security & 2FA') }}
            </flux:menu.item>
            <flux:menu.item :href="route('appearance.edit')" icon="paint-brush" wire:navigate class="text-xs font-medium">
                {{ __('Appearance') }}
            </flux:menu.item>
        </flux:menu.radio.group>

        <flux:menu.separator />

        <form method="POST" action="{{ route('logout') }}" class="w-full">
            @csrf
            <flux:menu.item
                as="button"
                type="submit"
                icon="arrow-right-start-on-rectangle"
                class="w-full cursor-pointer text-xs font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40"
                data-test="logout-button"
            >
                {{ __('Log out') }}
            </flux:menu.item>
        </form>
    </flux:menu>
</flux:dropdown>
