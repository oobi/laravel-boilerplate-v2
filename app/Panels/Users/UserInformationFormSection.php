<?php

declare(strict_types=1);

namespace App\Panels\Users;

use App\Enums\SystemRole;
use App\Support\Panels\Concerns\HasPanelMetadata;
use App\Support\Panels\Contracts\FormSection;
use Filament\Forms;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/** The core name/email/role/active fields on the Edit User form. */
class UserInformationFormSection implements FormSection
{
    use HasPanelMetadata;

    /** @return array<int, Component> */
    public function components(Model $subject): array
    {
        $isSelf = $subject->getKey() === Auth::id();

        return [
            Section::make(__('admin.user_information'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('first_name')
                        ->label(__('admin.first_name'))
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('last_name')
                        ->label(__('admin.last_name'))
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('email')
                        ->label(__('admin.email'))
                        ->email()
                        ->required()
                        ->maxLength(255),

                    Forms\Components\Select::make('system_role')
                        ->label(__('admin.system_role'))
                        ->options(SystemRole::options())
                        ->placeholder(__('admin.no_system_role'))
                        ->native(false)
                        ->disabled($isSelf),

                    Forms\Components\Toggle::make('active')
                        ->label(__('admin.active'))
                        ->disabled($isSelf),
                ]),
        ];
    }
}
