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
use App\Support\Audit\LogAuditEntry;
use App\Support\DisplayTimezone;
use App\Support\Settings\SettingsSections;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\LaravelSettings\Migrations\SettingsMigrator;
use Tests\Fixtures\PlainSecretWidgetSection;
use Tests\Fixtures\WidgetNoteSection;
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
        WidgetNoteSection::$visible = true;
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
        $this->assertSame(['organisation.timezone' => 'UTC'], $entry->attribute_changes['old']);
        $this->assertSame(['organisation.timezone' => 'Australia/Brisbane'], $entry->attribute_changes['attributes']);

        $this->actingAs(User::factory()->withPermission(SystemPermission::VIEW_AUDIT_LOG)->create())->get(route('audit.show', $entry))
            ->assertOk()
            ->assertSeeInOrder(['Time zone', 'organisation.timezone', 'UTC', 'Australia/Brisbane']);
    }

    public function test_a_save_that_changes_nothing_logs_nothing(): void
    {
        Livewire::actingAs(User::factory()->withPermission(SystemPermission::MANAGE_SETTINGS)->create())
            ->test(EditSettings::class)
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified(__('settings.unchanged'));

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
        $this->assertSame(['widgets.greeting' => 'Hello', 'widgets.api_key' => null], $entry->attribute_changes['old']);
        $this->assertSame(['widgets.greeting' => 'Welcome', 'widgets.api_key' => LogAuditEntry::REDACTED], $entry->attribute_changes['attributes']);
        $this->assertSame(['widgets.greeting' => 'Widget greeting', 'widgets.api_key' => 'Widget key'], $entry->attribute_changes['labels']);
        $this->assertStringNotContainsString('secret-key', $entry->getRawOriginal('attribute_changes'));
        $this->assertStringNotContainsString('secret-key', (string) $entry->getRawOriginal('properties'));
    }

    public function test_a_direct_save_ignores_a_setting_its_section_has_no_field_for(): void
    {
        $this->addWidgetSettings();
        $admin = User::factory()->withPermission(SystemPermission::MANAGE_SETTINGS)->create();

        $changed = app(SaveSettings::class)->handle(['widgets' => ['internal_note' => 'Sneaky']], $admin);

        $this->assertSame([], $changed);
        $this->assertSame('internal-only-note', app(WidgetSettings::class)->refresh()->internal_note);
    }

    public function test_a_section_sharing_a_settings_class_cant_write_what_only_a_hidden_section_shows(): void
    {
        $this->addWidgetSettings();
        SettingsSections::add(WidgetNoteSection::class);
        WidgetNoteSection::$visible = false;
        $admin = User::factory()->withPermission(SystemPermission::MANAGE_SETTINGS)->create();
        $sneaky = ['internal_note' => 'Sneaky'];

        $changed = app(SaveSettings::class)->handle(['widgets' => $sneaky, 'widget_notes' => $sneaky], $admin);

        $this->assertSame([], $changed);
        $this->assertSame('internal-only-note', app(WidgetSettings::class)->refresh()->internal_note);

        WidgetNoteSection::$visible = true;
        $this->assertSame(['widgets.internal_note'], app(SaveSettings::class)->handle(['widget_notes' => $sneaky], $admin));
    }

    public function test_a_settings_change_keeps_values_whatever_their_names_and_hides_every_secret(): void
    {
        $this->addWidgetSettings();
        SettingsSections::add(WidgetNoteSection::class);

        Livewire::actingAs(User::factory()->withPermission(SystemPermission::MANAGE_SETTINGS)->create())
            ->test(EditSettings::class)
            ->set('data.widget_notes.daily_token_allowance', 500)
            ->set('data.widgets.webhook_secret', ['value' => 'new-hook-pass'])
            ->call('save')
            ->assertHasNoFormErrors();

        $entry = AuditEntry::query()->where('event', 'settings.updated')->sole();
        $this->assertSame(['widgets.webhook_secret' => LogAuditEntry::REDACTED, 'widgets.daily_token_allowance' => 100], $entry->attribute_changes['old']);
        $this->assertSame(['widgets.webhook_secret' => LogAuditEntry::REDACTED, 'widgets.daily_token_allowance' => 500], $entry->attribute_changes['attributes']);
        $this->assertStringNotContainsString('plain-secret-value', $entry->getRawOriginal('attribute_changes'));
        $this->assertStringNotContainsString('new-hook-pass', $entry->getRawOriginal('attribute_changes'));
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
        $cleared = AuditEntry::query()->where('event', 'settings.updated')->latest('id')->first()->attribute_changes;
        $this->assertSame(['widgets.api_key' => LogAuditEntry::REDACTED], $cleared['old']);
        $this->assertSame(['widgets.api_key' => null], $cleared['attributes']);
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
        $migrator->add('widgets.daily_token_allowance', 100);

        SettingsSections::add(WidgetSettingsSection::class);
    }

    /** A new request resolves the settings afresh; within one test the instance is reused. */
    private function forgetSettings(): void
    {
        app()->forgetScopedInstances();
    }
}
