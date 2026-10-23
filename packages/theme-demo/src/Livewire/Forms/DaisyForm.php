<?php

declare(strict_types=1);

namespace Concise\ThemeDemo\Livewire\Forms;

use App\Enums\SystemPermission;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class DaisyForm extends Component
{
    use WithFileUploads;

    #[Validate('required|string|max:255')]
    public string $text = '';

    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required|string|min:8')]
    public string $password = '';

    #[Validate('nullable|integer|min:0')]
    public ?int $number = null;

    public string $textarea = '';

    public string $select = '';

    /** @var list<string> */
    public array $checkboxGroup = [];

    public string $radioGroup = '';

    public bool $toggle = false;

    public int $range = 50;

    public string $date = '';

    public mixed $file = null;

    public string $color = '#3b82f6';

    /** Deliberately left blank and pre-flagged so the error state is visible without any interaction. */
    public string $errorExample = '';

    public string $variant = 'floating';

    public function mount(string $variant = 'floating'): void
    {
        Gate::authorize(SystemPermission::ACCESS_ADMIN_PANEL->value);

        $this->variant = $variant;

        $this->showErrorExample();
    }

    public function submit(): void
    {
        $this->validate();

        session()->flash('status', __('Form submitted (demo only — nothing was saved).'));
    }

    /**
     * Empty every field (the demo's secondary action), keeping the label
     * variant and the error example.
     */
    public function clear(): void
    {
        $this->resetExcept('variant');
        $this->resetValidation();
        $this->showErrorExample();
    }

    /**
     * The field that always shows an error, so the gallery has the state.
     */
    private function showErrorExample(): void
    {
        $this->addError('errorExample', __('theme-demo::messages.field_error_message'));
    }

    public function render(): View
    {
        return view('theme-demo::livewire.forms.daisy')->layout('layouts.admin');
    }
}
