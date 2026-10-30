<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-parchment dark:bg-obsidian text-slate-900 dark:text-slate-100 antialiased font-sans">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-slate-200/80 bg-white/80 backdrop-blur-md dark:border-slate-800/80 dark:bg-obsidian">
            <flux:sidebar.header class="flex flex-col items-start gap-1.5 border-b border-slate-200/60 pb-3 dark:border-slate-800/60">
                <div class="flex w-full items-center justify-between">
                    <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                    <flux:sidebar.collapse class="lg:hidden" />
                </div>
                <div class="flex w-full items-center justify-between px-2 text-[10px] font-semibold tracking-widest text-slate-400 dark:text-slate-500 uppercase in-data-flux-sidebar-collapsed-desktop:hidden">
                    <span>Kadploy University</span>
                    <span class="inline-flex items-center rounded-xs bg-amber-500/15 px-1 py-0.5 font-mono text-[9px] font-bold text-amber-700 dark:text-amber-400">ERP</span>
                </div>
            </flux:sidebar.header>

            <flux:sidebar.nav class="gap-4">
                @if (auth()->user()->hasAnyRole(['admin', 'department-staff']))
                    <flux:sidebar.group :heading="__('Academic Operations')" class="grid gap-0.5">
                        <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                            {{ __('Dashboard') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="building-office-2" :href="route('departments.index')" :current="request()->routeIs('departments.*')" wire:navigate>
                            {{ __('Departments') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="book-open" :href="route('subjects.index')" :current="request()->routeIs('subjects.*')" wire:navigate>
                            {{ __('Subjects Catalog') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="academic-cap" :href="route('courses.index')" :current="request()->routeIs('courses.*')" wire:navigate>
                            {{ __('Course Sections') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="users" :href="route('students.directory')" :current="request()->routeIs('students.*')" wire:navigate>
                            {{ __('Student Roster') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="user-group" :href="route('faculty.index')" :current="request()->routeIs('faculty.*')" wire:navigate>
                            {{ __('Faculty Directory') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="clipboard-document-check" :href="route('enrollments.index')" :current="request()->routeIs('enrollments.*')" wire:navigate>
                            {{ __('Enrollments') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="question-mark-circle" :href="route('quizzes.index')" :current="request()->routeIs('quizzes.*')" wire:navigate>
                            {{ __('Quizzes & Assessments') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @elseif (auth()->user()->hasRole('faculty'))
                    <flux:sidebar.group :heading="__('Instruction')" class="grid gap-0.5">
                        <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                            {{ __('Dashboard') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="academic-cap" :href="route('courses.browse')" :current="request()->routeIs('courses.browse') || request()->routeIs('courses.home')" wire:navigate>
                            {{ __('My Classes') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="users" :href="route('students.my-classes')" :current="request()->routeIs('students.my-classes*')" wire:navigate>
                            {{ __('Class Roster') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="question-mark-circle" :href="route('quizzes.index')" :current="request()->routeIs('quizzes.*')" wire:navigate>
                            <div class="flex w-full items-center justify-between">
                                <span>{{ __('Assessment Hub') }}</span>
                                <span class="rounded bg-amber-500/15 px-1.5 py-0.5 font-mono text-[10px] font-bold text-amber-700 dark:text-amber-400">Quizzes</span>
                            </div>
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @else
                    <flux:sidebar.group :heading="__('Academic Space')" class="grid gap-0.5">
                        <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                            {{ __('Overview') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="academic-cap" :href="route('courses.browse')" :current="request()->routeIs('courses.browse') || request()->routeIs('courses.home')" wire:navigate>
                            {{ __('Enrolled Classes') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="question-mark-circle" :href="route('quizzes.index')" :current="request()->routeIs('quizzes.*')" wire:navigate>
                            {{ __('Quizzes & Exams') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="ticket" :href="route('enrollments.index')" :current="request()->routeIs('enrollments.*')" wire:navigate>
                            {{ __('Join Section') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile Institutional Header & Profile Drawer -->
        <flux:header class="border-b border-slate-200/80 bg-white/90 backdrop-blur-md dark:border-slate-800/80 dark:bg-obsidian lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <div class="flex items-center gap-2 pl-2">
                <span class="font-bold text-xs tracking-wider uppercase text-slate-900 dark:text-white">UNITY ACADEMIC</span>
                <span class="text-[10px] font-medium text-slate-400">Unity University</span>
            </div>

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                    class="border border-slate-200/60 dark:border-slate-800 rounded-lg p-1"
                />

                <flux:menu class="w-64 p-1.5 shadow-xl border border-slate-200/80 dark:border-slate-800">
                    <flux:menu.radio.group>
                        <div class="p-1 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                    class="size-8 bg-slate-900 text-white dark:bg-amber-500 dark:text-slate-950 font-semibold"
                                />

                                <div class="grid flex-1 text-start text-xs leading-tight min-w-0">
                                    <flux:heading class="truncate font-semibold">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate text-slate-500">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate class="text-xs font-medium">
                            {{ __('Settings') }}
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
        </flux:header>

        {{ $slot }}

        @fluxScripts
    </body>
</html>
