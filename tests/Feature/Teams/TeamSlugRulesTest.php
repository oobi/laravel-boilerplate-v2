<?php

namespace Tests\Feature\Teams;

use App\Models\User;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Livewire\Admin\Teams\TeamSettings;
use Concise\Teams\Livewire\Team\Settings;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A slug is a DNS label in host mode, so both Settings forms hold it to one in
 * every mode (GitHub oobi#29): a team can't save a slug it could never be
 * reached on.
 */
class TeamSlugRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Owners reach Settings through a role (ownership grants no visibility of its own).
        Team::createRole('Settings Admin', [TeamPermission::VIEW_SETTINGS, TeamPermission::UPDATE_TEAM]);
        config(['teams.default_owner_role' => 'Settings Admin']);
    }

    /** @return array<string, array{class-string}> */
    public static function forms(): array
    {
        return [
            'member settings' => [Settings::class],
            'admin settings' => [TeamSettings::class],
        ];
    }

    /** @return array<string, array{string}> */
    public static function unroutableSlugs(): array
    {
        return [
            'underscore' => ['acme_corp'],
            'leading hyphen' => ['-acme'],
            'trailing hyphen' => ['acme-'],
            'dot' => ['acme.corp'],
            'too long' => [str_repeat('a', 64)],
        ];
    }

    #[DataProvider('forms')]
    public function test_unroutable_slugs_are_rejected_in_every_mode(string $form): void
    {
        [$owner, $team] = $this->ownedTeam();

        foreach (self::unroutableSlugs() as [$slug]) {
            $this->settings($form, $owner, $team)
                ->set('data.slug', $slug)
                ->call('save')
                ->assertHasErrors('data.slug');

            $this->assertSame('acme', $team->fresh()->slug, "{$slug} was saved");
        }
    }

    #[DataProvider('forms')]
    public function test_a_reserved_slug_is_rejected_in_every_mode(string $form): void
    {
        [$owner, $team] = $this->ownedTeam();

        $this->settings($form, $owner, $team)
            ->set('data.slug', 'www')
            ->call('save')
            ->assertHasErrors('data.slug');

        config(['teams.domains.enabled' => true, 'teams.domains.admin_host' => 'admin.myapp.com', 'teams.domains.base' => 'myapp.com']);
        $this->settings($form, $owner, $team)
            ->set('data.slug', 'www')
            ->call('save')
            ->assertHasErrors('data.slug');

        $this->assertSame('acme', $team->fresh()->slug);
    }

    public function test_a_tampered_slug_is_a_validation_error_not_a_crash(): void
    {
        [$owner, $team] = $this->ownedTeam();

        $this->settings(TeamSettings::class, $owner, $team)
            ->set('data.slug', ['acme'])
            ->call('save')
            ->assertHasErrors('data.slug');
    }

    #[DataProvider('forms')]
    public function test_capitals_and_spaces_are_tidied_rather_than_rejected(string $form): void
    {
        [$owner, $team] = $this->ownedTeam();

        $this->settings($form, $owner, $team)
            ->set('data.slug', ' Acme-Corp ')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('acme-corp', $team->fresh()->slug);
    }

    public function test_a_slug_differing_only_in_case_is_taken(): void
    {
        Team::factory()->create(['slug' => 'beta']);
        [$owner, $team] = $this->ownedTeam();

        $this->settings(Settings::class, $owner, $team)
            ->set('data.slug', 'BETA')
            ->call('save')
            ->assertHasErrors(['data.slug' => 'unique']);
    }

    public function test_a_slug_is_stored_lower_cased_whatever_writes_it(): void
    {
        $this->assertSame('acme', Team::factory()->create(['slug' => ' ACME '])->slug);
    }

    public function test_generated_slugs_fit_in_one_dns_label(): void
    {
        $name = str_repeat('Long name ', 10);

        $first = Team::factory()->create(['name' => $name, 'slug' => null])->slug;
        $second = Team::factory()->create(['name' => $name, 'slug' => null])->slug;

        foreach ([$first, $second] as $slug) {
            $this->assertLessThanOrEqual(Team::SLUG_MAX_LENGTH, strlen($slug));
            $this->assertMatchesRegularExpression(Team::SLUG_PATTERN, $slug);
        }
        $this->assertNotSame($first, $second);
        $this->assertStringEndsWith('-2', $second);
    }

    /** @return array{User, Team} */
    private function ownedTeam(): array
    {
        $owner = User::factory()->create();

        return [$owner, Team::factory()->ownedBy($owner)->create(['slug' => 'acme'])];
    }

    private function settings(string $form, User $owner, Team $team): mixed
    {
        $actor = $form === TeamSettings::class ? User::factory()->superAdmin()->create() : $owner;

        return Livewire::actingAs($actor)->test($form, ['team' => $team]);
    }

    protected function tearDown(): void
    {
        app(TeamContext::class)->clear();

        parent::tearDown();
    }
}
