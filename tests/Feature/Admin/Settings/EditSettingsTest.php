<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Settings;

use App\Actions\Settings\SaveSettings;
use App\Enums\SystemPermission;
use App\Livewire\Admin\Settings\EditSettings;
use App\Livewire\Admin\Users\ShowUser;
use App\Models\AuditEntry;
use App\Models\User;
use App\Settings\OrganisationSettings;
use App\Support\DisplayTimezone;
use App\Support\Settings\SettingsSections;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\LaravelSettings\Migrations\SettingsMigrator;
use Tests\Fixtures\PlainSecretWidgetSection;
use Tests\Fixtures\WidgetSettings;
use Tests\Fixtures\WidgetSettingsSection;
use Tests\TestCase;

/** The admin Settings screen: who reaches it, saving the time zone, the audit entry, and add-on sections. */
class EditSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        WidgetSettingsSection::$visible = true;
    }

    public function test_only_people_who_manage_settings_see_the_link_and_reach_the_screen(): void
    {
        $admin = User::factory()->withPermission(SystemPermission::MANAGE_SETTINGS)->create();
        $other = User::factory()->withPermission(SystemPermission::VIEW_USERS)->create();

        $this->actingAs($admin)->get(route('dashboard'))->assertSeeHtml('href="'.route('settings.edit').'"');
        $this->actingAs($admin)->get(route('settings.edit'))->assertOk()->assertSee('Time zone');

        $this->actingAs($other)->get(route('dashboard'))->assertDontSeeHtml('href="'.route('settings.edit').'"');
        $this->actingAs($other)->get(route('settings.edit'))->assertForbidden();
    }

    public function test_choosing_a_time_zone_changes_the_zone_times_are_shown_in_and_is_logged(): void
    {
        $admin = User::factory()->withPermission(SystemPermission::MANAGE_SETTINGS)->create();
        $this->assertSame('UTC', DisplayTimezone::name());

        Livewire::actingAs($admin)
            ->test(EditSettings::class)
            ->assertSet('data.organisation.timezone', 'UTC')
            ->set('data.organisation.timezone', 'Australia/Brisbane')
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified(__('settings.saved'));

        $this->assertSame('Australia/Brisbane', app(OrganisationSettings::class)->refresh()->timezone);
        $this->forgetSettings();
        $this->assertSame('Australia/Brisbane', DisplayTimezone::name());

        $entry = AuditEntry::query()->where('event', 'settings.updated')->sole();
        $this->assertTrue($entry->causer->is($admin));
        $this->assertSame('system', $entry->log_name);
        $this->assertSame(['organisation.timezone'], $entry->properties['changed']);
        $this->assertSame(['organisation.timezone' => 'UTC'], $entry->properties['before']);
        $this->assertSame(['organisation.timezone' => 'Australia/Brisbane'], $entry->properties['after']);
    }

    public function test_a_save_that_changes_nothing_logs_nothing(): void
    {
        Livewire::actingAs(User::factory()->withPermission(SystemPermission::MANAGE_SETTINGS)->create())
            ->test(EditSettings::class)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse(AuditEntry::query()->where('event', 'settings.updated')->exists());
    }

    public function test_a_zone_that_isnt_one_is_refused(): void
    {
        Livewire::actingAs(User::factory()->withPermission(SystemPermission::MANAGE_SETTINGS)->create())
            ->test(EditSettings::class)
            ->set('data.organisation.timezone', 'Brisbane')
            ->call('save')
            ->assertHasFormErrors(['organisation.timezone'])
            ->set('data.organisation.timezone', null)
            ->call('save')
            ->assertHasFormErrors(['organisation.timezone' => 'required']);

        $this->assertSame('UTC', app(OrganisationSettings::class)->refresh()->timezone);
    }

    public function test_losing_manage_settings_with_the_screen_open_refuses_the_next_update(): void
    {
        $admin = User::factory()->withPermission(SystemPermission::MANAGE_SETTINGS)->create();
        $page = $this->actingAs($admin)->get(route('settings.edit'))->assertOk();

        $admin->syncPermissions([SystemPermission::ACCESS_ADMIN_PANEL->value]);
        $admin->unsetRelation('permissions');

        $this->livewireUpdate($page)->assertForbidden();
    }

    public function test_an_add_on_section_shows_and_saves_and_its_encrypted_values_stay_out_of_the_log(): void
    {
        $this->addWidgetSettings();
        $admin = User::factory()->withPermission(SystemPermission::MANAGE_SETTINGS)->create();

        Livewire::actingAs($admin)
            ->test(EditSettings::class)
            ->assertSee('Widget greeting')
            ->assertSet('data.widgets.greeting', 'Hello')
            ->set('data.widgets.greeting', 'Welcome')
            ->set('data.widgets.api_key', ['value' => 'secret-key'])
            ->call('save')
            ->assertHasNoFormErrors();

        $widgets = app(WidgetSettings::class)->refresh();
        $this->assertSame('Welcome', $widgets->greeting);
        $this->assertSame('secret-key', $widgets->api_key);

        $entry = AuditEntry::query()->where('event', 'settings.updated')->sole();
        $this->assertSame(['widgets.greeting', 'widgets.api_key'], $entry->properties['changed']);
        $this->assertSame(['widgets.greeting' => 'Welcome'], $entry->properties['after']);
        $this->assertStringNotContainsString('secret-key', $entry->getRawOriginal('properties'));
    }

    public function test_a_section_the_person_cant_see_is_neither_shown_nor_saved(): void
    {
        $this->addWidgetSettings();
        WidgetSettingsSection::$visible = false;
        $admin = User::factory()->withPermission(SystemPermission::MANAGE_SETTINGS)->create();

        Livewire::actingAs($admin)->test(EditSettings::class)->assertDontSee('Widget greeting');

        $changed = app(SaveSettings::class)->handle(['widgets' => ['greeting' => 'Sneaky']], $admin);

        $this->assertSame([], $changed);
        $this->assertSame('Hello', app(WidgetSettings::class)->refresh()->greeting);
    }

    public function test_a_locked_setting_keeps_its_value(): void
    {
        $this->addWidgetSettings();
        app(WidgetSettings::class)->lock('greeting');
        $admin = User::factory()->withPermission(SystemPermission::MANAGE_SETTINGS)->create();

        $changed = app(SaveSettings::class)->handle(['widgets' => ['greeting' => 'Changed']], $admin);

        $this->assertSame([], $changed);
        $this->assertFalse(AuditEntry::query()->where('event', 'settings.updated')->exists());
        $this->assertSame('Hello', app(WidgetSettings::class)->refresh()->greeting);
    }

    public function test_saving_needs_manage_settings_whoever_calls_it(): void
    {
        $this->expectException(AuthorizationException::class);

        app(SaveSettings::class)->handle(['organisation' => ['timezone' => 'Asia/Dubai']], User::factory()->withPermission(SystemPermission::VIEW_USERS)->create());
    }

    public function test_a_stored_secret_and_a_setting_with_no_field_never_reach_the_page(): void
    {
        $this->addWidgetSettings();
        app(WidgetSettings::class)->fill(['api_key' => 'secret-key'])->save();

        $this->actingAs(User::factory()->withPermission(SystemPermission::MANAGE_SETTINGS)->create())
            ->get(route('settings.edit'))
            ->assertOk()
            ->assertSeeHtml('aria-label="Replace key: Widget key"')
            ->assertSeeHtml('aria-label="Remove key: Widget key"')
            ->assertSeeHtml('autocomplete="new-password"')
            ->assertDontSeeHtml('<x-filament::input')
            ->assertDontSee('secret-key')
            ->assertDontSee('internal-only-note');
    }

    public function test_saving_without_touching_a_secret_keeps_it_and_saving_it_empty_clears_it(): void
    {
        $this->addWidgetSettings();
        app(WidgetSettings::class)->fill(['api_key' => 'secret-key'])->save();
        $admin = User::factory()->withPermission(SystemPermission::MANAGE_SETTINGS)->create();

        Livewire::actingAs($admin)
            ->test(EditSettings::class)
            ->assertSet('data.widgets.api_key', null)
            ->set('data.widgets.greeting', 'Welcome')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('secret-key', app(WidgetSettings::class)->refresh()->api_key);

        Livewire::actingAs($admin)
            ->test(EditSettings::class)
            ->set('data.widgets.api_key', ['value' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull(app(WidgetSettings::class)->refresh()->api_key);
        $this->assertSame(['widgets.api_key'], AuditEntry::query()->where('event', 'settings.updated')->latest('id')->first()->properties['changed']);
    }

    public function test_with_no_secret_stored_the_field_offers_set_and_no_clear(): void
    {
        $this->addWidgetSettings();

        $this->actingAs(User::factory()->withPermission(SystemPermission::MANAGE_SETTINGS)->create())
            ->get(route('settings.edit'))
            ->assertSeeHtml('aria-label="Add key: Widget key"')
            ->assertDontSeeHtml('aria-label="Remove key: Widget key"');
    }

    public function test_filament_date_columns_show_the_chosen_zone(): void
    {
        OrganisationSettings::fake(['timezone' => 'Australia/Brisbane']);
        $user = User::factory()->create(['created_at' => '2026-11-11 15:00:00']);

        Livewire::actingAs(User::factory()->withPermission(SystemPermission::VIEW_USERS)->create())
            ->test(ShowUser::class, ['user' => $user])
            ->assertSee('Nov 12, 2026 01:00:00');
    }

    public function test_a_secret_field_never_sends_what_it_was_filled_with(): void
    {
        $this->addWidgetSettings();

        $this->actingAs(User::factory()->withPermission(SystemPermission::MANAGE_SETTINGS)->create())
            ->get(route('settings.edit'))
            ->assertOk()
            ->assertDontSee('plain-secret-value');

        Livewire::actingAs(User::factory()->withPermission(SystemPermission::MANAGE_SETTINGS)->create())
            ->test(EditSettings::class)
            ->assertSet('data.widgets.webhook_secret', null);
    }

    public function test_an_encrypted_setting_shown_without_a_secret_field_is_refused(): void
    {
        $this->addWidgetSettings();
        SettingsSections::flush();
        SettingsSections::add(PlainSecretWidgetSection::class);

        // Livewire's render wraps the LogicException in a view exception; the message carries through.
        $this->expectExceptionMessage('Show the encrypted setting [widgets.api_key] with a SecretInput.');

        Livewire::actingAs(User::factory()->withPermission(SystemPermission::MANAGE_SETTINGS)->create())
            ->test(EditSettings::class);
    }

    public function test_a_required_secret_left_alone_passes_while_one_is_stored_but_cant_be_emptied(): void
    {
        $this->addWidgetSettings();
        $page = Livewire::actingAs(User::factory()->withPermission(SystemPermission::MANAGE_SETTINGS)->create())
            ->test(EditSettings::class);

        $page->call('save')->assertHasNoFormErrors();

        $page->set('data.widgets.webhook_secret', ['value' => ''])
            ->call('save')
            ->assertHasFormErrors(['widgets.webhook_secret' => 'required']);

        $this->assertSame('plain-secret-value', app(WidgetSettings::class)->refresh()->webhook_secret);
    }

    public function test_a_required_secret_offers_no_remove(): void
    {
        $this->addWidgetSettings();
        app(WidgetSettings::class)->fill(['api_key' => 'secret-key'])->save();

        $this->actingAs(User::factory()->withPermission(SystemPermission::MANAGE_SETTINGS)->create())
            ->get(route('settings.edit'))
            ->assertSeeHtml('aria-label="Remove key: Widget key"')
            ->assertSeeHtml('aria-label="Replace value: Webhook secret"')
            ->assertDontSeeHtml('aria-label="Remove value: Webhook secret"');
    }

    public function test_a_secret_fields_rules_check_what_was_typed(): void
    {
        $this->addWidgetSettings();
        $page = Livewire::actingAs(User::factory()->withPermission(SystemPermission::MANAGE_SETTINGS)->create())
            ->test(EditSettings::class);

        $page->set('data.widgets.webhook_secret', ['value' => str_repeat('a', 21)])
            ->call('save')
            ->assertHasFormErrors(['widgets.webhook_secret' => 'max']);

        $page->set('data.widgets.webhook_secret', ['value' => ['tampered']])
            ->call('save')
            ->assertHasFormErrors(['widgets.webhook_secret' => 'string']);

        $this->assertSame('plain-secret-value', app(WidgetSettings::class)->refresh()->webhook_secret);

        $page->set('data.widgets.webhook_secret', ['value' => 'new-secret'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('new-secret', app(WidgetSettings::class)->refresh()->webhook_secret);
    }

    public function test_after_a_save_a_secret_field_goes_back_to_saying_whats_stored(): void
    {
        $this->addWidgetSettings();

        Livewire::actingAs(User::factory()->withPermission(SystemPermission::MANAGE_SETTINGS)->create())
            ->test(EditSettings::class)
            ->assertSeeHtml('aria-label="Add key: Widget key"')
            ->set('data.widgets.api_key', ['value' => 'brand-new'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertSet('data.widgets.api_key', null)
            ->assertSeeHtml('aria-label="Replace key: Widget key"')
            ->assertDontSee('brand-new');
    }

    private function addWidgetSettings(): void
    {
        $migrator = app(SettingsMigrator::class);
        $migrator->add('widgets.greeting', 'Hello');
        $migrator->addEncrypted('widgets.api_key', null);
        $migrator->add('widgets.internal_note', 'internal-only-note');
        $migrator->add('widgets.webhook_secret', 'plain-secret-value');

        SettingsSections::add(WidgetSettingsSection::class);
    }

    /** A new request resolves the settings afresh; within one test the instance is reused. */
    private function forgetSettings(): void
    {
        app()->forgetScopedInstances();
    }
}
