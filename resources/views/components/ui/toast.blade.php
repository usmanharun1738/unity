@props(['message' => null, 'variant' => 'success'])

@if (filled($message))
    <div class="pointer-events-none fixed right-4 top-4 z-50 w-full max-w-sm">
        <flux:callout variant="{{ $variant === 'error' ? 'danger' : 'success' }}" icon="{{ $variant === 'error' ? 'exclamation-triangle' : 'check-circle' }}">
            {{ $message }}
        </flux:callout>
    </div>
@endif
