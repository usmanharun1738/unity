<?php

namespace App\Livewire\Concerns;

trait HasToastFeedback
{
    public ?string $toastMessage = null;

    public string $toastVariant = 'success';

    protected function successToast(string $message, bool $persist = false): void
    {
        $this->toastVariant = 'success';
        $this->toastMessage = $message;

        if ($persist) {
            session()->flash('toastMessage', $message);
            session()->flash('toastVariant', 'success');
        }
    }

    protected function errorToast(string $message, bool $persist = false): void
    {
        $this->toastVariant = 'error';
        $this->toastMessage = $message;

        if ($persist) {
            session()->flash('toastMessage', $message);
            session()->flash('toastVariant', 'error');
        }
    }

    protected function clearToast(): void
    {
        $this->toastMessage = null;
        $this->toastVariant = 'success';
    }

    protected function pullToastFromSession(): void
    {
        $this->toastMessage = session('toastMessage');
        $this->toastVariant = session('toastVariant', 'success');
    }
}
