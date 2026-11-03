<?php

namespace Tests\Feature\Admin\Users;

use App\Livewire\Admin\Users\ListUsers;
use App\Models\User;
use App\Support\UserListColumns;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * An add-on can add a column to the admin Users list, loading what it shows
 * through the list's own query (UserListColumns). A stand-in column here, so
 * this runs without the teams tier.
 */
class UserListColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_registered_column_shows_what_its_query_hook_loads(): void
    {
        UserListColumns::register(
            'shouted_email',
            fn (): Column => TextColumn::make('shouted_email')->label('Shouted'),
            fn (Builder $query): Builder => $query->addSelect(DB::raw('upper(email) as shouted_email')),
        );
        $user = User::factory()->create(['email' => 'quiet@example.com']);

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ListUsers::class)
            ->assertTableColumnExists('shouted_email')
            ->assertTableColumnStateSet('shouted_email', 'QUIET@EXAMPLE.COM', $user);
    }
}
