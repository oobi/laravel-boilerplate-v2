<?php

declare(strict_types=1);

namespace Concise\ThemeDemo\Livewire\Forms;

use App\Enums\SystemPermission;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Slider;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/** Filament equivalent of the daisyUI form demo — same field concepts, plus two Filament-exclusive extras (TagsInput, MarkdownEditor). */
class FilamentForm extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        Gate::authorize(SystemPermission::ACCESS_ADMIN_PANEL->value);

        $this->form->fill();

        $this->addError('data.error_example', __('theme-demo::messages.field_error_message'));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('text')->label(__('theme-demo::messages.field_text'))->required(),
                TextInput::make('email')->label(__('theme-demo::messages.field_email'))->email()->required(),
                TextInput::make('password')->label(__('theme-demo::messages.field_password'))->password()->revealable(),
                TextInput::make('number')->label(__('theme-demo::messages.field_number'))->numeric(),
                Textarea::make('textarea')->label(__('theme-demo::messages.field_textarea'))->columnSpanFull(),
                Select::make('select')->label(__('theme-demo::messages.field_select'))->options([
                    'one' => __('Option one'),
                    'two' => __('Option two'),
                    'three' => __('Option three'),
                ]),
                CheckboxList::make('checkbox_group')->label(__('theme-demo::messages.field_checkbox_group'))->options([
                    'a' => 'Option A',
                    'b' => 'Option B',
                    'c' => 'Option C',
                ]),
                Radio::make('radio_group')->label(__('theme-demo::messages.field_radio_group'))->options([
                    'x' => 'Option X',
                    'y' => 'Option Y',
                    'z' => 'Option Z',
                ]),
                Toggle::make('toggle')->label(__('theme-demo::messages.field_toggle')),
                Slider::make('range')->label(__('theme-demo::messages.field_range'))->default(50),
                DatePicker::make('date')->label(__('theme-demo::messages.field_date')),
                FileUpload::make('file')->label(__('theme-demo::messages.field_file')),
                ColorPicker::make('color')->label(__('theme-demo::messages.field_color'))->default('#3b82f6'),
                TextInput::make('error_example')->label(__('theme-demo::messages.field_error_example')),
                TagsInput::make('tags')->label(__('theme-demo::messages.field_tags'))->columnSpanFull(),
                MarkdownEditor::make('markdown')->label(__('theme-demo::messages.field_markdown'))->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $this->form->getState();

        session()->flash('status', __('Form submitted (demo only — nothing was saved).'));
    }

    public function render(): View
    {
        return view('theme-demo::livewire.forms.filament')->layout('layouts.admin');
    }
}
