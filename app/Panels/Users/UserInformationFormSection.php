<?php

declare(strict_types=1);

namespace App\Panels\Users;

use App\Support\Panels\Concerns\HasPanelMetadata;
use App\Support\Panels\Contracts\FormSection;
use Filament\Forms;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * The core name/email/active fields on the Edit User form. Role and
 * super-admin assignment are their own distinct, separately-authorized
 * actions on EditUser — never part of this generic form state (see
 * UserPolicy::assignRole()/grantSuperAdmin() docblocks).
 */
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

                    Forms\Components\Toggle::make('active')
                        ->label(__('admin.active'))
                        ->disabled($isSelf || ! Gate::allows('toggleActive', $subject)),
                ]),
        ];
    }
}
