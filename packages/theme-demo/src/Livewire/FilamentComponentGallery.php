<?php

declare(strict_types=1);

namespace Concise\ThemeDemo\Livewire;

use App\Enums\SystemPermission;
use App\Enums\UserStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class FilamentComponentGallery extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    /** Maps daisyUI's 8 semantic color names to Filament's own color keys (gray/danger differ). */
    public array $filamentColors = [
        'primary' => 'primary',
        'secondary' => 'secondary',
        'accent' => 'accent',
        'neutral' => 'gray',
        'info' => 'info',
        'success' => 'success',
        'warning' => 'warning',
        'error' => 'danger',
    ];

    /** @var list<string> */
    public array $sizes = ['xs', 'sm', 'md', 'lg', 'xl'];

    public function mount(): void
    {
        Gate::authorize(SystemPermission::ACCESS_ADMIN_PANEL->value);
    }

    /**
     * `->status()` on Filament entries (columns take it the same way): each
     * state's dot from the entry's colour or an enum's HasColor, the label as
     * plain text; optional size and animation, as <x-status>.
     */
    public function statusSchema(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 2, 'md' => 4])->schema([
                ...collect(UserStatus::cases())->map(fn (UserStatus $status): TextEntry => TextEntry::make("status_{$status->value}")
                    ->label(__('Enum: :label', ['label' => $status->getLabel()]))
                    ->state($status)
                    ->status()),
                TextEntry::make('status_large')
                    ->label(__('Size lg'))
                    ->state(__('Suspended'))
                    ->color('warning')
                    ->status(size: 'lg'),
                TextEntry::make('status_ping')
                    ->label(__('Animation ping'))
                    ->state(__('Live'))
                    ->color('danger')
                    ->status(animation: 'ping'),
            ]),
        ]);
    }

    public function sendNotification(string $status): void
    {
        Notification::make()
            ->title(match ($status) {
                'success' => __('Success! Everything worked.'),
                'danger' => __('Something went wrong.'),
                'warning' => __('Careful — check this.'),
                default => __('Just so you know…'),
            })
            ->status($status)
            ->send();
    }

    public function render(): View
    {
        return view('theme-demo::livewire.filament-component-gallery')->layout('layouts.admin');
    }
}
