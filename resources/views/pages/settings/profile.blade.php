<?php

use App\Concerns\ProfileValidationRules;
use App\Enums\RoleName;
use App\Models\StudentProfile;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Profile settings')] class extends Component
{
    use ProfileValidationRules;
    use WithFileUploads;

    public string $name = '';

    public string $email = '';

    public string $major = '';

    public ?int $year_level = null;

    public string $bio = '';

    public string $avatar_path = '';

    /** @var mixed */
    public $avatar_upload = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;

        $studentProfile = Auth::user()->studentProfile;

        $this->major = $studentProfile?->major ?? '';
        $this->year_level = $studentProfile?->year_level;
        $this->bio = $studentProfile?->bio ?? '';
        $this->avatar_path = $studentProfile?->avatar_path ?? '';
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            ...$this->profileRules(Auth::id()),
            'major' => ['nullable', 'string', 'max:120'],
            'year_level' => ['nullable', 'integer', 'between:1,8'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'avatar_upload' => ['nullable', 'image', 'max:2048'],
        ];
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate();

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($this->avatar_upload !== null) {
            if ($this->avatar_path !== '' && str_starts_with($this->avatar_path, 'avatars/') && Storage::disk('public')->exists($this->avatar_path)) {
                Storage::disk('public')->delete($this->avatar_path);
            }

            $this->avatar_path = $this->avatar_upload->store('avatars', 'public');
            $this->avatar_upload = null;
        }

        if ($user->hasRole(RoleName::Student->value)) {
            StudentProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'student_number' => $user->studentProfile?->student_number
                        ?? 'STU-'.strtoupper(substr(sha1((string) $user->id.$user->email), 0, 8)),
                    'major' => $validated['major'] !== '' ? $validated['major'] : null,
                    'year_level' => $validated['year_level'],
                    'bio' => $validated['bio'] !== '' ? $validated['bio'] : null,
                    'avatar_path' => $this->avatar_path !== '' ? $this->avatar_path : null,
                ],
            );
        }

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }

    #[Computed]
    public function isStudent(): bool
    {
        return Auth::user()->hasRole(RoleName::Student->value);
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your name, email, and profile information')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                @if ($this->hasUnverifiedEmail)
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Your email address is unverified.') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>

                        @if (session('status') === 'verification-link-sent')
                            <flux:text class="mt-2 font-medium text-green-600! dark:text-green-400!">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </flux:text>
                        @endif
                    </div>
                @endif
            </div>

            @if ($this->isStudent)
                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:heading size="sm">{{ __('Student Profile') }}</flux:heading>
                    <flux:subheading class="mt-1">{{ __('Manage your academic profile details.') }}</flux:subheading>

                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <flux:input wire:model="major" :label="__('Major')" type="text" />

                        <flux:input wire:model="year_level" :label="__('Year level')" type="number" min="1" max="8" />

                        <flux:input wire:model="avatar_upload" :label="__('Avatar image')" type="file" accept="image/*" class="md:col-span-2" />

                        <div class="md:col-span-2">
                            @if ($avatar_upload)
                                <img src="{{ $avatar_upload->temporaryUrl() }}" alt="{{ __('Avatar preview') }}" class="h-20 w-20 rounded-full object-cover" />
                            @elseif ($avatar_path !== '')
                                <img src="{{ Storage::disk('public')->url($avatar_path) }}" alt="{{ __('Avatar') }}" class="h-20 w-20 rounded-full object-cover" />
                            @else
                                <flux:text>{{ __('No avatar uploaded yet.') }}</flux:text>
                            @endif
                        </div>

                        <flux:input wire:model="bio" :label="__('Bio')" type="text" class="md:col-span-2" />
                    </div>
                </div>
            @endif

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                        {{ __('Save') }}
                    </flux:button>
                </div>

                <x-action-message class="me-3" on="profile-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>

        @if ($this->showDeleteUser)
            <livewire:pages::settings.delete-user-form />
        @endif
    </x-pages::settings.layout>
</section>
