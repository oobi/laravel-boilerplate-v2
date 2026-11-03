<?php

declare(strict_types=1);

namespace Tests\Feature\Components;

use App\Enums\UserStatus;
use App\Livewire\Admin\Users\ListUsers;
use App\Models\User;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** A state as daisyUI's status dot and its label (GitHub #73): <x-status> and Filament's ->status(). */
class StatusComponentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_decorative_dot_then_the_label(): void
    {
        $this->blade('<x-status color="warning">Suspended</x-status>')
            ->assertSeeInOrder(['class="status status-warning" aria-hidden="true"', 'Suspended'], false);
    }

    public function test_an_enum_gives_the_colour_and_label_mapped_to_daisyui(): void
    {
        $this->blade('<x-status :value="$status" />', ['status' => UserStatus::ACTIVE])
            ->assertSee('status-success', false)
            ->assertSee(UserStatus::ACTIVE->getLabel());

        // Filament's palette names map across (gray is daisyUI's neutral).
        $this->blade('<x-status color="gray">Off</x-status>')->assertSee('status-neutral', false);
    }

    public function test_daisyuis_sizes_and_animations_are_optional_and_respect_reduced_motion(): void
    {
        $this->blade('<x-status color="success" size="lg">On</x-status>')->assertSee('status-lg', false);

        $this->blade('<x-status color="error" animation="ping">Live</x-status>')
            ->assertSee('motion-safe:animate-ping', false)
            ->assertSee('inline-grid', false);

        $this->blade('<x-status color="info" animation="bounce">New</x-status>')
            ->assertSee('motion-safe:animate-bounce', false);

        // Anything else is ignored rather than emitted as a class.
        $this->blade('<x-status color="info" size="huge" animation="spin">New</x-status>')
            ->assertDontSee('status-huge', false)
            ->assertDontSee('animate-spin', false);
    }

    public function test_filament_columns_and_entries_show_the_dot_by_their_colour_and_keep_the_label(): void
    {
        $user = User::factory()->make(['active' => false]);

        $column = TextColumn::make('status')->status()->record($user);
        $this->assertStringContainsString('status-neutral', (string) $column->formatState($user->status));
        $this->assertStringContainsString(UserStatus::INACTIVE->getLabel(), (string) $column->formatState($user->status));

        $entry = TextEntry::make('active')
            ->formatStateUsing(fn (bool $state): string => $state ? 'On <b>' : 'Off')
            ->color(fn (bool $state): string => $state ? 'success' : 'gray')
            ->status(size: 'sm')
            ->container(Schema::make()->record($user));

        $html = (string) $entry->formatState(true);
        $this->assertStringContainsString('status-success status-sm', $html);
        $this->assertStringContainsString('On &lt;b&gt;', $html, 'the label is still escaped');
    }

    public function test_a_column_with_a_description_keeps_the_dot_on_its_label(): void
    {
        $user = User::factory()->superAdmin()->create(['active' => false]);
        $table = Livewire::actingAs($user)->test(ListUsers::class)->instance()->getTable();

        // With a description Filament nests the label in its own item; the marker
        // goes on the cell and the theme CSS lays out the item, so it can't land
        // on the column wrapper as flex utilities did (oobi #76).
        $html = TextColumn::make('status')->status()->description('1 hour ago')->table($table)->record($user)->toEmbeddedHtml();

        $this->assertMatchesRegularExpression('/class="[^"]*\bfi-ta-text-has-descriptions\b[^"]*\bui-status\b|class="[^"]*\bui-status\b[^"]*\bfi-ta-text-has-descriptions\b/', $html);
        $this->assertMatchesRegularExpression('/class="[^"]*\bfi-ta-text-item\b[^"]*"[^>]*>\s*<span class="status status-neutral"/', $html);
        $this->assertStringNotContainsString('items-center', $html);
    }
}
