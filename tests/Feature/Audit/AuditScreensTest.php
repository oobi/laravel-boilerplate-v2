<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Enums\SystemPermission;
use App\Livewire\Admin\Audit\ListAuditEntries;
use App\Livewire\Admin\Audit\ShowAuditEntry;
use App\Livewire\Admin\Users\ShowUser;
use App\Models\AuditEntry;
use App\Models\Role;
use App\Models\User;
use App\Panels\Users\ActivityPanel;
use App\Support\Audit\Audit;
use App\Support\Audit\AuditActor;
use App\Support\Audit\AuditCategory;
use App\Support\Audit\AuditDisplay;
use App\Support\Navigation\Registry\NavGroup;
use App\Support\Navigation\Registry\NavRegistry;
use App\Support\Panels\Registry\PanelRegistry;
use App\Support\Theme\DaisyColor;
use Database\Seeders\DemoSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Fixtures\AbstractAuditedRecord;
use Tests\TestCase;

class AuditScreensTest extends TestCase
{
    use RefreshDatabase;

    private const string TRAIL = '01KAAB3XJ2Q8M7N4P5R6S7T8V9';

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get(route('audit.index'))->assertRedirect(route('login'));
    }

    public function test_an_admin_without_the_permission_is_refused_every_screen(): void
    {
        $admin = User::factory()->withPermission(SystemPermission::VIEW_USERS)->create();
        $entry = AuditEntry::factory()->create();

        $this->actingAs($admin)->get(route('audit.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('audit.show', $entry))->assertForbidden();
        $this->actingAs($admin)->post(route('audit.export'))->assertForbidden();
    }

    public function test_the_view_permission_opens_the_screens_but_not_the_export(): void
    {
        $viewer = User::factory()->withPermission(SystemPermission::VIEW_AUDIT_LOG)->create();
        $entry = AuditEntry::factory()->create();

        $this->actingAs($viewer)->get(route('audit.index'))->assertOk()->assertDontSee(__('audit.export_csv'));
        $this->actingAs($viewer)->get(route('audit.show', $entry))->assertOk();
        $this->actingAs($viewer)->post(route('audit.export'))->assertForbidden();
    }

    public function test_access_lost_with_a_page_open_is_refused_at_the_next_click(): void
    {
        $viewer = User::factory()->withPermission(SystemPermission::VIEW_AUDIT_LOG)->create();
        $page = $this->actingAs($viewer)->get(route('audit.index'));

        $viewer->syncPermissions([SystemPermission::ACCESS_ADMIN_PANEL->value]);
        $viewer->unsetRelation('permissions');
        $viewer->roles()->detach();

        $this->livewireUpdate($page)->assertForbidden();
    }

    public function test_the_list_shows_who_did_what_to_what_and_links_it(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create(['first_name' => 'Target', 'last_name' => 'Person']);
        $this->actingAs($admin);
        Audit::record('invoice.sent', $target, description: 'Invoice sent to the target');

        $entry = AuditEntry::query()->where('event', 'invoice.sent')->sole();

        Livewire::test(ListAuditEntries::class)
            ->assertSee('Invoice sent to the target')
            ->assertSee($admin->name)
            ->assertSee('Target Person')
            ->assertSeeHtml(route('audit.show', $entry))
            ->assertSeeHtml(route('users.show', $target));
    }

    public function test_only_an_actor_without_a_persons_name_has_a_badge(): void
    {
        AuditEntry::factory()->create(['actor_type' => AuditActor::USER, 'actor_name' => 'Named Person']);
        AuditEntry::factory()->create(['actor_type' => AuditActor::GUEST, 'actor_name' => null]);

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ListAuditEntries::class)
            ->assertSee('Named Person')
            ->assertSeeHtml('badge badge-'.AuditDisplay::actorColor(AuditActor::GUEST)->value)
            ->assertDontSeeHtml('badge badge-'.AuditDisplay::actorColor(AuditActor::USER)->value);
    }

    public function test_a_person_with_no_stored_name_still_has_a_badge(): void
    {
        AuditEntry::factory()->create(['actor_type' => AuditActor::USER, 'actor_name' => null]);

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ListAuditEntries::class)
            ->assertSeeHtml('badge badge-'.AuditDisplay::actorColor(AuditActor::USER)->value);
    }

    public function test_the_newest_entry_comes_first(): void
    {
        AuditEntry::factory()->create(['description' => 'Older entry', 'created_at' => now()->subDay()]);
        AuditEntry::factory()->create(['description' => 'Newer entry', 'created_at' => now()]);

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ListAuditEntries::class)
            ->assertSeeInOrder(['Newer entry', 'Older entry']);
    }

    public function test_it_filters_by_category_event_and_actor_type(): void
    {
        AuditEntry::factory()->create(['description' => 'Kept entry', 'log_name' => 'auth', 'event' => 'auth.login', 'actor_type' => AuditActor::GUEST]);
        AuditEntry::factory()->create(['description' => 'Other entry', 'log_name' => 'data', 'event' => 'x.y', 'actor_type' => AuditActor::SYSTEM]);
        $component = Livewire::actingAs(User::factory()->superAdmin()->create())->test(ListAuditEntries::class);

        foreach (['category' => 'auth', 'event' => 'auth.login', 'actor_type' => AuditActor::GUEST] as $filter => $value) {
            $component->set("tableFilters.{$filter}.value", $value)
                ->assertSee('Kept entry')
                ->assertDontSee('Other entry')
                ->set("tableFilters.{$filter}.value", null);
        }
    }

    public function test_it_filters_by_date_in_the_apps_timezone(): void
    {
        AuditEntry::factory()->create(['description' => 'Kept entry', 'created_at' => '2026-03-10 12:00:00']);
        AuditEntry::factory()->create(['description' => 'Other entry', 'created_at' => '2026-03-11 12:00:00']);

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ListAuditEntries::class)
            ->set('tableFilters.date.value', '2026-03-10')
            ->assertSee('Kept entry')
            ->assertDontSee('Other entry');
    }

    public function test_it_searches_descriptions_events_people_and_addresses_with_wildcards_taken_literally(): void
    {
        AuditEntry::factory()->create(['description' => 'Kept entry', 'event' => 'parcel.sent', 'actor_name' => 'Ada Lovelace', 'ip_address' => '10.0.0.7']);
        AuditEntry::factory()->create(['description' => 'Other entry', 'event' => 'parcel.lost', 'actor_name' => 'Grace Hopper', 'ip_address' => '10.0.0.8']);
        $component = Livewire::actingAs(User::factory()->superAdmin()->create())->test(ListAuditEntries::class);

        foreach (['Kept', 'parcel.sent', 'Lovelace', '10.0.0.7'] as $term) {
            $component->set('tableSearch', $term)->assertSee('Kept entry')->assertDontSee('Other entry');
        }

        $component->set('tableSearch', '%')->assertDontSee('Kept entry')->assertDontSee('Other entry');
    }

    public function test_searching_a_users_email_or_name_finds_what_they_did_and_what_was_done_to_them(): void
    {
        $person = User::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.test']);
        $other = User::factory()->create(['first_name' => 'Grace', 'last_name' => 'Hopper', 'email' => 'grace@example.test']);
        Audit::record('did.something', by: $person, description: 'They did this');
        Audit::record('done.to.them', $person, by: $other, description: 'This was done to them');
        Audit::record('unrelated.thing', $other, by: $other, description: 'Nothing to do with them');
        AuditEntry::factory()->create([
            'description' => 'Another kind of record with the same id',
            'subject_type' => Role::class,
            'subject_id' => $person->getKey(),
            'causer_type' => Role::class,
            'causer_id' => $person->getKey(),
        ]);
        $component = Livewire::actingAs(User::factory()->superAdmin()->create())->test(ListAuditEntries::class);

        foreach (['ada@example.test', 'ADA@EXAMPLE', 'Lovelace'] as $term) {
            $component->set('tableSearch', $term)
                ->assertSee('They did this')
                ->assertSee('This was done to them')
                ->assertDontSee('Nothing to do with them')
                ->assertDontSee('Another kind of record with the same id');
        }
    }

    public function test_searching_a_soft_deleted_users_email_still_finds_their_entries(): void
    {
        $person = User::factory()->create(['email' => 'gone@example.test']);
        Audit::record('done.to.them', $person, description: 'This was done to them');
        $person->delete();

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ListAuditEntries::class)
            ->set('tableSearch', 'gone@example')
            ->assertSee('This was done to them');
    }

    public function test_searching_the_address_typed_into_a_failed_login_finds_it_with_no_account(): void
    {
        AuditEntry::factory()->create(['description' => 'Kept entry', 'properties' => ['identifier' => 'nobody@example.test']]);
        AuditEntry::factory()->create(['description' => 'Other entry', 'properties' => ['identifier' => 'someone@example.test']]);

        $component = Livewire::actingAs(User::factory()->superAdmin()->create())->test(ListAuditEntries::class);

        // Uppercase too: MySQL compares JSON text case-sensitively.
        foreach (['nobody@example', 'NOBODY@Example'] as $term) {
            $component->set('tableSearch', $term)->assertSee('Kept entry')->assertDontSee('Other entry');
        }
    }

    public function test_the_list_shows_the_email_of_who_did_it_who_it_applies_to_and_a_failed_login(): void
    {
        // Created and deleted unlogged, so only the entries below can show each address.
        [$actor, $target, $deleted] = Audit::withoutLogging(fn (): array => [
            User::factory()->create(['email' => 'actor@example.test']),
            User::factory()->create(['email' => 'target@example.test']),
            User::factory()->create(['email' => 'deleted@example.test']),
        ]);
        Audit::record('done.to.them', $target, by: $actor);
        Audit::record('note.added', by: $deleted);
        Audit::withoutLogging(fn () => $deleted->delete());
        AuditEntry::factory()->create(['properties' => ['identifier' => 'typed@example.test']]);

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ListAuditEntries::class)
            ->assertSee('actor@example.test')
            ->assertSee('target@example.test')
            ->assertSee('deleted@example.test')
            ->assertSeeHtml('href="'.route('users.show', $deleted).'"')
            ->assertSee('typed@example.test');
    }

    public function test_the_export_finds_a_users_entries_and_a_failed_login_by_email(): void
    {
        $exporter = User::factory()->superAdmin()->create();
        $person = User::factory()->create(['email' => 'ada@example.test']);
        Audit::record('done.to.them', $person, description: 'This was done to them');
        AuditEntry::factory()->create(['description' => 'Failed sign in', 'properties' => ['identifier' => 'ada@example.test']]);
        AuditEntry::factory()->create(['description' => 'Unrelated entry']);

        $rows = $this->exportRows($this->actingAs($exporter)->post(route('audit.export'), ['search' => 'ada@example'])->streamedContent());

        $this->assertContains('This was done to them', $rows->pluck('description'));
        $this->assertContains('Failed sign in', $rows->pluck('description'));
        $this->assertNotContains('Unrelated entry', $rows->pluck('description'));
    }

    public function test_a_persons_link_shows_what_they_did_and_what_was_done_to_them(): void
    {
        $person = User::factory()->create(['first_name' => 'Named', 'last_name' => 'Person']);
        $other = User::factory()->create();
        Audit::record('did.something', by: $person, description: 'They did this');
        Audit::record('done.to.them', $person, by: $other, description: 'This was done to them');
        Audit::record('unrelated.thing', $other, by: $other, description: 'Nothing to do with them');
        AuditEntry::factory()->create([
            'description' => 'Another kind of record with the same id',
            'subject_type' => Role::class,
            'subject_id' => $person->getKey(),
            'causer_type' => Role::class,
            'causer_id' => $person->getKey(),
        ]);

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->withQueryParams(['user' => (string) $person->getKey()])
            ->test(ListAuditEntries::class)
            ->assertSee('They did this')
            ->assertSee('This was done to them')
            ->assertDontSee('Nothing to do with them')
            ->assertDontSee('Another kind of record with the same id')
            ->assertSee(__('audit.showing_for', ['name' => 'Named Person']));
    }

    public function test_a_user_in_the_url_that_could_not_be_an_id_is_no_filter_at_all(): void
    {
        AuditEntry::factory()->create(['description' => 'Kept entry']);
        $superAdmin = User::factory()->superAdmin()->create();

        foreach (['not-a-number', '1.5', '-5', '0', '99999999999999999999'] as $value) {
            Livewire::actingAs($superAdmin)
                ->withQueryParams(['user' => $value])
                ->test(ListAuditEntries::class)
                ->assertSee('Kept entry')
                ->assertDontSee(__('audit.show_everyone'))
                ->assertDontSeeHtml('name="user"');
        }

        foreach (['user[]=1', 'user=[1]', 'user={"a":1}', 'user=%FF'] as $query) {
            $this->actingAs($superAdmin)->get(route('audit.index').'?'.$query)->assertOk();
        }
    }

    public function test_a_user_who_no_longer_exists_still_narrows_the_list_and_says_so(): void
    {
        AuditEntry::factory()->create(['description' => 'Unrelated entry']);

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->withQueryParams(['user' => '999999'])
            ->test(ListAuditEntries::class)
            ->assertDontSee('Unrelated entry')
            ->assertSee(__('audit.showing_for_deleted'));
    }

    public function test_a_soft_deleted_user_still_narrows_the_list_by_name(): void
    {
        $person = User::factory()->create(['first_name' => 'Named', 'last_name' => 'Person']);
        $person->delete();

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->withQueryParams(['user' => (string) $person->getKey()])
            ->test(ListAuditEntries::class)
            ->assertSee(__('audit.showing_for', ['name' => 'Named Person']))
            ->assertDontSee(__('audit.showing_for_deleted'));
    }

    public function test_an_entry_whose_class_no_longer_exists_still_shows_on_every_screen(): void
    {
        $viewer = User::factory()->superAdmin()->create();
        $person = User::factory()->create(['first_name' => 'Named', 'last_name' => 'Person']);
        Audit::record('note.added', $person, description: 'Entry about a person');
        $gone = AuditEntry::factory()->create([
            'description' => 'Entry about a removed record',
            'subject_type' => 'Concise\Removed\Team',
            'subject_id' => 7,
            'causer_type' => 'Concise\Removed\Robot',
            'causer_id' => 3,
        ]);
        // The name now belongs to a class that isn't a model.
        $replaced = AuditEntry::factory()->create([
            'description' => 'Entry about a replaced record',
            'subject_type' => AuditDisplay::class,
            'subject_id' => 8,
            'causer_type' => AuditDisplay::class,
            'causer_id' => 9,
        ]);
        $abstract = AuditEntry::factory()->create([
            'description' => 'Entry about an abstract record',
            'subject_type' => AbstractAuditedRecord::class,
            'subject_id' => 10,
            'causer_type' => AbstractAuditedRecord::class,
            'causer_id' => 11,
        ]);

        Livewire::actingAs($viewer)->test(ListAuditEntries::class)
            ->assertSee('Entry about a removed record')
            ->assertSee('Team 7')
            ->assertSee('Entry about a replaced record')
            ->assertSee('Audit Display 8')
            ->assertSee('Entry about an abstract record')
            ->assertSee('Abstract Audited Record 10')
            ->assertSee('Entry about a person')
            ->assertSee('Named Person');

        $this->actingAs($viewer)->get(route('audit.show', $gone))->assertOk()->assertSee('Team 7');
        $this->actingAs($viewer)->get(route('audit.show', $replaced))->assertOk()->assertSee('Audit Display 8');
        $this->actingAs($viewer)->get(route('audit.show', $abstract))->assertOk()->assertSee('Abstract Audited Record 10');
    }

    public function test_the_number_of_queries_does_not_grow_with_the_rows_on_the_page(): void
    {
        $viewer = User::factory()->superAdmin()->create();
        $this->actingAs($viewer);

        foreach (range(1, 30) as $number) {
            Audit::record('some.entry', User::factory()->create(), by: User::factory()->create());
        }

        $page = Livewire::test(ListAuditEntries::class);
        $page->set('tableRecordsPerPage', 5);
        $this->queriesWhileRefreshing($page);
        $fewRows = $this->queriesWhileRefreshing($page);

        $page->set('tableRecordsPerPage', 25);

        $this->assertSame($fewRows, $this->queriesWhileRefreshing($page));
    }

    public function test_the_filter_dropdowns_read_in_words_and_are_cached_for_five_minutes(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        AuditEntry::factory()->create(['event' => 'auth.login', 'log_name' => 'auth', 'actor_type' => 'guest']);
        AuditEntry::factory()->create(['event' => 'invoice.sent', 'log_name' => 'data', 'actor_type' => 'user']);
        AuditEntry::factory()->create(['event' => 'step10.done', 'log_name' => 'security']);
        AuditEntry::factory()->create(['event' => 'step2.done']);

        $page = fn (): ListAuditEntries => Livewire::test(ListAuditEntries::class)->instance();
        $options = fn (): array => $page()->eventOptions();

        $first = $options();

        $this->assertSame('Logged in', $first['auth.login']);
        $this->assertSame('Invoice sent', $first['invoice.sent']);

        $events = array_keys($first);
        $this->assertLessThan(array_search('step10.done', $events), array_search('step2.done', $events));

        foreach ([$first, $page()->categoryOptions(), $page()->actorTypeOptions()] as $dropdown) {
            $labels = array_values($dropdown);
            $sorted = $labels;
            sort($sorted, SORT_NATURAL | SORT_FLAG_CASE);
            $this->assertSame($sorted, $labels);
        }

        AuditEntry::factory()->create(['event' => 'parcel.lost']);

        $this->assertArrayNotHasKey('parcel.lost', $options());

        $this->travel(6)->minutes();

        $this->assertArrayHasKey('parcel.lost', $options());
    }

    public function test_an_id_that_could_not_exist_is_not_found_on_any_database(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        foreach (['abc', '1.5', '99999999999999999999'] as $id) {
            $this->get(route('audit.index')."/{$id}")->assertNotFound();
        }

        $this->get(route('audit.export'))->assertMethodNotAllowed();
    }

    public function test_the_detail_heading_is_the_event_and_a_written_description_is_shown_whole(): void
    {
        $description = str_repeat('a', 500);
        $written = AuditEntry::factory()->create(['event' => 'note.added', 'description' => $description]);
        $noEvent = AuditEntry::factory()->create(['event' => null, 'description' => str_repeat('b', 500)]);
        $viewer = User::factory()->superAdmin()->create();

        Livewire::actingAs($viewer)->test(ShowAuditEntry::class, ['entry' => $written])
            ->assertSeeHtml('<h1 class="ui-page-title">Note added</h1>')
            ->assertSee($description);

        Livewire::actingAs($viewer)->test(ShowAuditEntry::class, ['entry' => $noEvent])
            ->assertSeeHtml('<h1 class="ui-page-title">'.Str::limit(str_repeat('b', 500), 120).'</h1>');
    }

    public function test_the_detail_page_says_what_happened_as_a_sentence(): void
    {
        $viewer = User::factory()->superAdmin()->create(['first_name' => 'Super', 'last_name' => 'Admin']);
        $person = User::factory()->create(['first_name' => 'Michel', 'last_name' => 'Adams']);
        $this->actingAs($viewer);
        $person->update(['last_name' => 'J. Adams']);
        Audit::record('user.roles_changed', $person, category: AuditCategory::ACCESS);
        Audit::record('invoice.sent', $person);
        Audit::record('payout.approved', description: 'Payout approved for INV-12');
        $entry = fn (string $event): AuditEntry => AuditEntry::query()->where('event', $event)->sole();

        $this->assertSame('Super Admin updated Michel J. Adams', AuditDisplay::summary($entry('user.updated')));
        $this->assertSame('Super Admin changed the roles of Michel J. Adams', AuditDisplay::summary($entry('user.roles_changed')));
        $this->assertNull(AuditDisplay::summary($entry('invoice.sent')));
        $this->assertSame('Payout approved for INV-12', AuditDisplay::summary($entry('payout.approved')));

        Livewire::test(ShowAuditEntry::class, ['entry' => $entry('user.updated')])
            ->assertSeeInOrder(['User updated', 'Super Admin updated Michel J. Adams', 'user.updated']);
    }

    public function test_everything_a_visitor_could_have_typed_is_escaped_on_every_screen(): void
    {
        $marker = '<b>typed</b>';
        $person = User::factory()->create(['first_name' => $marker]);
        Audit::record('note.added', $person, description: $marker, by: $person);
        $superAdmin = User::factory()->superAdmin()->create();

        Livewire::actingAs($superAdmin)
            ->withQueryParams(['user' => (string) $person->getKey()])
            ->test(ListAuditEntries::class)
            ->set('tableSearch', $marker)
            ->assertSee($marker)
            ->assertSeeHtml('name="search" value="'.e($marker).'"')
            ->assertDontSeeHtml($marker);

        Livewire::actingAs($superAdmin)
            ->test(ShowUser::class, ['user' => $person])
            ->assertSeeHtml(e($marker))
            ->assertDontSeeHtml($marker);

        // Without a link to wrap them: names and the impersonator, read by someone who can't open people.
        AuditEntry::factory()->create(['description' => 'Impersonated entry', 'actor_name' => $marker, 'impersonator_name' => $marker, 'subject_type' => User::class, 'subject_id' => $person->getKey()]);

        Livewire::actingAs(User::factory()->withPermission(SystemPermission::VIEW_AUDIT_LOG)->create())
            ->test(ListAuditEntries::class)
            ->assertSee('Impersonated entry')
            ->assertSeeHtml(e($marker))
            ->assertDontSeeHtml($marker);
    }

    public function test_an_export_searched_for_zero_is_recorded_with_that_search(): void
    {
        $this->actingAs(User::factory()->withPermission(SystemPermission::EXPORT_AUDIT_LOG)->create())
            ->post(route('audit.export'), ['search' => '0', 'category' => ''])
            ->assertOk();

        $this->assertSame(['search' => '0'], AuditEntry::query()->where('event', 'audit.exported')->sole()->getProperty('filters'));
    }

    public function test_a_person_is_only_linked_for_someone_who_may_open_them(): void
    {
        $person = User::factory()->create();
        Audit::record('note.added', by: $person);

        $mayOpen = User::factory()->withPermission(SystemPermission::VIEW_AUDIT_LOG, SystemPermission::VIEW_USERS)->create();
        $mayNot = User::factory()->withPermission(SystemPermission::VIEW_AUDIT_LOG)->create();

        Livewire::actingAs($mayOpen)->test(ListAuditEntries::class)->assertSeeHtml(route('users.show', $person));
        Livewire::actingAs($mayNot)->test(ListAuditEntries::class)->assertSee($person->name)->assertDontSeeHtml(route('users.show', $person));
    }

    public function test_a_role_is_only_linked_for_someone_who_may_edit_roles(): void
    {
        $role = Role::create(['name' => 'Editor', 'guard_name' => 'web']);
        Audit::record('note.added', $role);
        $entry = AuditEntry::query()->where('event', 'note.added')->sole();

        $this->actingAs(User::factory()->withPermission(SystemPermission::MANAGE_ROLES)->create());
        $this->assertSame(['url' => route('roles.edit', $role), 'label' => 'Editor'], AuditDisplay::subject($entry));

        $this->actingAs(User::factory()->withPermission(SystemPermission::VIEW_AUDIT_LOG)->create());
        $this->assertSame(['url' => null, 'label' => 'Editor'], AuditDisplay::subject($entry));
    }

    public function test_the_detail_page_names_the_event_in_words_with_its_code(): void
    {
        $signedIn = AuditEntry::factory()->create(['event' => 'auth.login']);
        $projectEvent = AuditEntry::factory()->create(['event' => 'parcel.lost']);
        $viewer = User::factory()->superAdmin()->create();

        Livewire::actingAs($viewer)->test(ShowAuditEntry::class, ['entry' => $signedIn])
            ->assertSeeHtml('<h1 class="ui-page-title">Logged in</h1>')
            ->assertSee('auth.login');

        Livewire::actingAs($viewer)->test(ShowAuditEntry::class, ['entry' => $projectEvent])
            ->assertSeeHtml('<h1 class="ui-page-title">'.Audit::describe('parcel.lost').'</h1>')
            ->assertSee('parcel.lost');
    }

    public function test_an_event_that_reads_the_same_as_its_code_is_not_shown_twice(): void
    {
        $entry = AuditEntry::factory()->create(['event' => 'created', 'description' => 'Something']);

        $html = Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ShowAuditEntry::class, ['entry' => $entry])
            ->assertSee('Created')
            ->html();

        $this->assertSame(1, substr_count(strtolower(strip_tags($html)), 'created'));
    }

    public function test_stored_values_and_core_events_read_in_words(): void
    {
        $this->assertSame(__('audit.empty_value'), AuditDisplay::value(null));
        $this->assertSame(__('audit.true'), AuditDisplay::value(true));
        $this->assertSame(__('audit.false'), AuditDisplay::value(false));
        $this->assertSame('["a"]', AuditDisplay::value(['a']));

        $this->assertSame('Logged in', Audit::describe('auth.login'));
        $this->assertSame('Invoice sent', Audit::describe('invoice.sent'));
        $this->assertSame('Invoice permanently deleted', Audit::describe('invoice.force_deleted'));
    }

    public function test_the_detail_page_shows_before_and_after_and_escapes_what_it_shows(): void
    {
        $viewer = User::factory()->superAdmin()->create();
        $user = User::factory()->create(['first_name' => 'Before']);
        $this->actingAs($viewer);

        $user->update(['first_name' => '<script>alert(1)</script>']);
        $entry = AuditEntry::query()->where('event', 'user.updated')->sole();

        Livewire::test(ShowAuditEntry::class, ['entry' => $entry])
            ->assertSeeInOrder(['First name', 'first_name', 'Before', '<script>alert(1)</script>'])
            ->assertDontSeeHtml('<script>alert(1)</script>')
            ->assertSee(trans_choice('audit.field_count', 1));
    }

    public function test_the_changed_part_of_a_short_similar_value_is_highlighted_and_nothing_else(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        $person = User::factory()->create(['email' => 'jo@example.com', 'first_name' => 'Jo']);
        $person->update(['email' => 'jo@example.com.au', 'first_name' => 'Christopher']);

        Livewire::test(ShowAuditEntry::class, ['entry' => AuditEntry::query()->where('event', 'user.updated')->sole()])
            ->assertSeeHtml('jo@example.com<mark class="ui-diff-added">.au</mark>')
            ->assertSee('Christopher')
            ->assertDontSeeHtml('<mark class="ui-diff-added">Christopher</mark>')
            ->assertSee('Email address');
    }

    public function test_every_part_of_a_change_and_the_performer_are_escaped(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        $entry = AuditEntry::factory()->create([
            'event' => 'invoice.updated',
            'actor_name' => '<b>actor</b>',
            'attribute_changes' => [
                'old' => ['note' => '<b>before</b>', 'tag' => 'a<i>'],
                'attributes' => ['note' => 'plain', 'tag' => 'a<i>x<u>'],
            ],
        ]);

        Livewire::test(ShowAuditEntry::class, ['entry' => $entry])
            ->assertSeeHtml(e('<b>before</b>'))
            ->assertSeeHtml('a&lt;i&gt;<mark class="ui-diff-added">x&lt;u&gt;</mark>')
            ->assertSeeHtml(e('<b>actor</b>'))
            ->assertDontSeeHtml('<b>before</b>')
            ->assertDontSeeHtml('<u>')
            ->assertDontSeeHtml('<b>actor</b>');
    }

    public function test_an_accented_change_is_highlighted_whole_and_a_list_reads_as_indented_json(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        $accented = AuditEntry::factory()->create(['event' => 'shop.updated', 'attribute_changes' => [
            'old' => ['name' => 'Café', 'sign' => 'Cafe'],
            // The accent typed as its own mark: it stays with its letter.
            'attributes' => ['name' => 'Cafés', 'sign' => "Cafe\u{0301}"],
        ]]);
        $listed = AuditEntry::factory()->create(['properties' => ['tags' => ['red', 'blue']]]);

        Livewire::test(ShowAuditEntry::class, ['entry' => $accented])
            ->assertSeeHtml('Café<mark class="ui-diff-added">s</mark>')
            ->assertSeeHtml("Caf<mark class=\"ui-diff-added\">e\u{0301}</mark>");

        Livewire::test(ShowAuditEntry::class, ['entry' => $listed])
            ->assertSeeHtml("<pre class=\"font-mono text-sm whitespace-pre-wrap wrap-anywhere\">[\n    &quot;red&quot;,\n    &quot;blue&quot;\n]</pre>");
    }

    public function test_a_created_record_has_no_before_a_deleted_one_no_after_and_hidden_values_say_so(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        $created = AuditEntry::factory()->create(['event' => 'invoice.created', 'attribute_changes' => ['attributes' => ['total' => 10, 'note' => null]]]);
        $deleted = AuditEntry::factory()->create(['event' => 'invoice.deleted', 'attribute_changes' => ['old' => ['total' => 10]]]);
        $hidden = AuditEntry::factory()->create(['event' => 'invoice.updated', 'attribute_changes' => ['old' => ['api_key' => '[redacted]'], 'attributes' => ['api_key' => '[redacted]']]]);

        Livewire::test(ShowAuditEntry::class, ['entry' => $created])
            ->assertSee(__('audit.after'))
            ->assertDontSee(__('audit.before'))
            ->assertSee(__('audit.empty_value'));

        Livewire::test(ShowAuditEntry::class, ['entry' => $deleted])
            ->assertSee(__('audit.before'))
            ->assertDontSee(__('audit.after'));

        Livewire::test(ShowAuditEntry::class, ['entry' => $hidden])
            ->assertSee(__('audit.hidden_value'))
            ->assertDontSee('[redacted]');
    }

    public function test_the_detail_page_shows_other_properties_in_words(): void
    {
        $viewer = User::factory()->superAdmin()->create();
        $this->actingAs($viewer);
        Audit::record('user.roles_changed', properties: ['before' => ['Support'], 'after' => ['Editor'], 'holder_count' => 3], category: AuditCategory::ACCESS);

        Livewire::test(ShowAuditEntry::class, ['entry' => AuditEntry::query()->where('event', 'user.roles_changed')->sole()])
            ->assertSee(__('audit.event_data'))
            ->assertSee('"Support"')
            ->assertSee('"Editor"')
            ->assertSeeInOrder(['People who held it', 'holder_count', '3']);
    }

    public function test_the_detail_page_links_the_person_who_did_it_only_for_someone_who_may_open_them(): void
    {
        $person = User::factory()->create(['first_name' => 'Named', 'last_name' => 'Person']);
        Audit::record('note.added', by: $person);
        $entry = AuditEntry::query()->where('event', 'note.added')->sole();

        Livewire::actingAs(User::factory()->withPermission(SystemPermission::VIEW_AUDIT_LOG, SystemPermission::VIEW_USERS)->create())
            ->test(ShowAuditEntry::class, ['entry' => $entry])
            ->assertSeeHtml(route('users.show', $person));

        Livewire::actingAs(User::factory()->withPermission(SystemPermission::VIEW_AUDIT_LOG)->create())
            ->test(ShowAuditEntry::class, ['entry' => $entry])
            ->assertSee('Named Person')
            ->assertDontSeeHtml(route('users.show', $person));
    }

    public function test_request_details_show_only_when_a_browser_was_recorded(): void
    {
        $viewer = User::factory()->superAdmin()->create();
        $web = AuditEntry::factory()->create(['user_agent' => 'Mozilla/5.0 TestBrowser']);
        $console = AuditEntry::factory()->create(['user_agent' => null]);

        Livewire::actingAs($viewer)->test(ShowAuditEntry::class, ['entry' => $web])
            ->assertSee(__('audit.request_details'))
            ->assertSee('Mozilla/5.0 TestBrowser');

        Livewire::actingAs($viewer)->test(ShowAuditEntry::class, ['entry' => $console])
            ->assertDontSee(__('audit.request_details'));
    }

    public function test_a_subject_that_no_longer_exists_still_says_what_it_was(): void
    {
        $viewer = User::factory()->superAdmin()->create();
        $user = User::factory()->create();
        $id = $user->getKey();
        Audit::record('note.added', $user);
        $user->forceDelete();

        $subject = AuditDisplay::subject(AuditEntry::query()->where('event', 'note.added')->sole());

        $this->actingAs($viewer);
        $this->assertSame("User {$id}", $subject['label']);
        $this->assertNull($subject['url']);
    }

    public function test_a_subject_is_only_linked_for_someone_who_may_open_it(): void
    {
        $viewer = User::factory()->withPermission(SystemPermission::VIEW_AUDIT_LOG)->create();
        $user = User::factory()->create(['first_name' => 'Named', 'last_name' => 'Person']);
        Audit::record('note.added', $user);
        $this->actingAs($viewer);

        $subject = AuditDisplay::subject(AuditEntry::query()->where('event', 'note.added')->sole());

        $this->assertSame('Named Person', $subject['label']);
        $this->assertNull($subject['url']);
    }

    public function test_a_project_can_register_its_own_actor_type_and_subject(): void
    {
        AuditDisplay::registerActor('crm', 'CRM sync', DaisyColor::SUCCESS);
        AuditDisplay::registerSubject(User::class, fn (User $user): array => ['label' => 'Custom '.$user->getKey(), 'url' => 'https://example.test/x']);
        $user = User::factory()->create();
        Audit::as('crm', fn () => Audit::record('contact.synced', $user));

        $entry = AuditEntry::query()->where('event', 'contact.synced')->sole();

        $this->assertSame('CRM sync', AuditDisplay::actorLabel('crm'));
        $this->assertSame(DaisyColor::SUCCESS, AuditDisplay::actorColor('crm'));
        $this->assertSame(['url' => 'https://example.test/x', 'label' => 'Custom '.$user->getKey()], AuditDisplay::subject($entry));
    }

    public function test_a_project_names_its_own_category_and_event_without_editing_core_lang(): void
    {
        AuditDisplay::registerCategory('billing', 'Invoices and payments');
        AuditDisplay::registerCategory(AuditCategory::AUTH, 'Access log');
        AuditDisplay::registerEvent('invoice.sent', 'Invoice emailed', ':actor emailed an invoice to :subject');
        AuditDisplay::registerEvent('invoice.voided', 'Invoice cancelled');
        $viewer = User::factory()->superAdmin()->create(['first_name' => 'Super', 'last_name' => 'Admin']);
        $customer = User::factory()->create(['first_name' => 'Jo', 'last_name' => 'Customer']);
        $this->actingAs($viewer);

        Audit::record('invoice.sent', $customer, category: 'billing');
        Audit::record('invoice.voided', $customer, category: 'billing');
        $sent = AuditEntry::query()->where('event', 'invoice.sent')->sole();
        $voided = AuditEntry::query()->where('event', 'invoice.voided')->sole();

        $this->assertSame('Invoice emailed', $sent->description);
        $this->assertSame('Super Admin emailed an invoice to Jo Customer', AuditDisplay::summary($sent));
        $this->assertNull(AuditDisplay::summary($voided));

        // A registered name wins over the tidied fallback and over core's own wording.
        $this->assertSame('Invoices and payments', AuditDisplay::categoryLabel('billing'));
        $this->assertSame('Access log', AuditDisplay::categoryLabel(AuditCategory::AUTH->value));

        $page = Livewire::test(ListAuditEntries::class);
        $this->assertSame('Invoices and payments', $page->instance()->categoryOptions()['billing']);
        $this->assertSame('Invoice emailed', $page->instance()->eventOptions()['invoice.sent']);
        $page->assertSee('Invoices and payments');

        Livewire::test(ShowAuditEntry::class, ['entry' => $sent])
            ->assertSeeHtml('<h1 class="ui-page-title">Invoice emailed</h1>')
            ->assertSee('Super Admin emailed an invoice to Jo Customer');
    }

    public function test_an_entry_with_no_event_shows_its_description_once_as_the_heading(): void
    {
        $entry = AuditEntry::factory()->create(['event' => null, 'description' => 'Bare description here']);

        $html = Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ShowAuditEntry::class, ['entry' => $entry])
            ->assertSeeHtml('<h1 class="ui-page-title">Bare description here</h1>')
            ->html();

        $this->assertNull(AuditDisplay::summary($entry));
        $this->assertSame(1, substr_count(strip_tags($html), 'Bare description here'));
    }

    public function test_a_section_with_nothing_to_show_and_an_empty_main_column_are_left_out(): void
    {
        $viewer = User::factory()->superAdmin()->create();
        $changesOnly = AuditEntry::factory()->create(['attribute_changes' => ['old' => ['total' => 1], 'attributes' => ['total' => 2]], 'properties' => [], 'user_agent' => null]);
        $bare = AuditEntry::factory()->create(['attribute_changes' => null, 'properties' => [], 'user_agent' => null]);

        Livewire::actingAs($viewer)->test(ShowAuditEntry::class, ['entry' => $changesOnly])
            ->assertSee(__('audit.changes'))
            ->assertDontSee(__('audit.event_data'))
            ->assertDontSee(__('audit.request_details'));

        Livewire::actingAs($viewer)->test(ShowAuditEntry::class, ['entry' => $bare])
            ->assertDontSee(__('audit.changes'))
            ->assertDontSeeHtml('lg:col-span-2')
            ->assertSee(__('audit.event_context'));
    }

    public function test_a_written_description_shows_on_the_detail_page_before_any_sentence(): void
    {
        $viewer = User::factory()->superAdmin()->create(['first_name' => 'Super', 'last_name' => 'Admin']);
        $person = User::factory()->create(['first_name' => 'Jo', 'last_name' => 'Bloggs']);
        $this->actingAs($viewer);
        Audit::record('user.deactivated', $person, description: 'Deactivated after 5 failed logins', category: AuditCategory::ACCESS);
        $entry = AuditEntry::query()->where('event', 'user.deactivated')->sole();

        $this->assertSame('Deactivated after 5 failed logins', AuditDisplay::summary($entry));

        Livewire::test(ShowAuditEntry::class, ['entry' => $entry])
            ->assertSee('Deactivated after 5 failed logins')
            ->assertDontSee('Super Admin deactivated Jo Bloggs');
    }

    public function test_an_entry_written_before_its_event_had_wording_still_reads_as_a_sentence(): void
    {
        $viewer = User::factory()->superAdmin()->create(['first_name' => 'Super', 'last_name' => 'Admin']);
        $this->actingAs($viewer);
        // Written while the event read as its code tidied, then given words and a sentence.
        Audit::record('invoice.sent', $viewer);
        AuditDisplay::registerEvent('invoice.sent', 'Invoice emailed', ':actor emailed an invoice to :subject');
        $entry = AuditEntry::query()->where('event', 'invoice.sent')->sole();

        $this->assertSame('Invoice sent', $entry->description);
        $this->assertFalse(AuditDisplay::hasWrittenDescription($entry));
        $this->assertSame('Super Admin emailed an invoice to Super Admin', AuditDisplay::summary($entry));
    }

    public function test_a_history_imported_under_a_morph_map_reads_on_the_list_detail_and_person_filter(): void
    {
        // Shaped like CDB's: a morph map, its own actor type, entries backdated from the old table.
        $previousMap = Relation::morphMap();
        Relation::morphMap(['crew_role' => Role::class, 'person' => User::class]);

        try {
            $person = User::factory()->create(['first_name' => 'Mapped', 'last_name' => 'Person']);
            Audit::record('note.added', $person, description: 'Entry about a mapped person');
            AuditDisplay::registerActor('import', 'Import', DaisyColor::ACCENT);
            $role = Role::create(['name' => 'Editor', 'guard_name' => 'web']);
            Audit::as('import', function () use ($role): void {
                Audit::record('rules.reordered', $role, description: 'Imported entry about a role', at: now()->subYear());
                AuditEntry::factory()->create([
                    'description' => 'Imported entry about a run',
                    'subject_type' => 'import_run',
                    'subject_id' => 5,
                    'created_at' => now()->subYear(),
                ]);
            }, actorName: 'CDB history');
            $entry = AuditEntry::query()->where('event', 'rules.reordered')->sole();
            $viewer = User::factory()->superAdmin()->create();

            $this->assertSame('crew_role', $entry->subject_type);
            $this->assertTrue($entry->created_at->isSameDay(now()->subYear()));

            Livewire::actingAs($viewer)->test(ListAuditEntries::class)
                ->assertSee('Imported entry about a role')
                ->assertSee('CDB history')
                ->assertSeeHtml('badge badge-'.DaisyColor::ACCENT->value)
                ->assertSeeHtml(route('roles.edit', $role))
                ->assertSee('Imported entry about a run')
                ->assertSee('Import Run 5');

            $this->actingAs($viewer)->get(route('audit.show', $entry))->assertOk()->assertSee(route('roles.edit', $role), false);

            Livewire::actingAs($viewer)
                ->withQueryParams(['user' => (string) $person->getKey()])
                ->test(ListAuditEntries::class)
                ->assertSee('Entry about a mapped person')
                ->assertDontSee('Imported entry about a role');
        } finally {
            Relation::morphMap($previousMap, false);
        }
    }

    public function test_an_unknown_actor_type_or_category_is_still_readable(): void
    {
        $this->assertSame('Claude Agent', AuditDisplay::actorLabel('claude_agent'));
        $this->assertSame('Ledger', AuditDisplay::categoryLabel('ledger'));
    }

    public function test_the_export_holds_what_the_filters_show_and_is_itself_recorded(): void
    {
        $exporter = User::factory()->withPermission(SystemPermission::EXPORT_AUDIT_LOG)->create();
        AuditEntry::factory()->create(['description' => 'Kept entry', 'log_name' => 'auth']);
        AuditEntry::factory()->create(['description' => 'Other entry', 'log_name' => 'data']);

        $response = $this->actingAs($exporter)->post(route('audit.export'), ['category' => 'auth']);

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Kept entry', $csv);
        $this->assertStringNotContainsString('Other entry', $csv);

        $recorded = AuditEntry::query()->where('event', 'audit.exported')->sole();

        $this->assertTrue($recorded->causer->is($exporter));
        $this->assertSame(['category' => 'auth'], $recorded->getProperty('filters'));
    }

    public function test_each_filter_on_the_screen_narrows_the_export(): void
    {
        $person = User::factory()->create();
        $exporter = User::factory()->withPermission(SystemPermission::EXPORT_AUDIT_LOG)->create();
        AuditEntry::factory()->create([
            'description' => 'Kept entry',
            'log_name' => 'auth',
            'event' => 'auth.login',
            'actor_type' => AuditActor::GUEST,
            'created_at' => '2026-03-10 12:00:00',
            'causer_type' => $person->getMorphClass(),
            'causer_id' => $person->getKey(),
            'session_trail' => self::TRAIL,
        ]);
        AuditEntry::factory()->create([
            'description' => 'Other entry',
            'log_name' => 'data',
            'event' => 'x.y',
            'actor_type' => AuditActor::SYSTEM,
            'created_at' => '2026-03-11 12:00:00',
        ]);

        $filters = [
            'category' => 'auth',
            'event' => 'auth.login',
            'actor_type' => AuditActor::GUEST,
            'date' => '2026-03-10',
            'search' => 'Kept',
            'user' => (string) $person->getKey(),
            'session' => self::TRAIL,
        ];

        foreach ($filters as $filter => $value) {
            $csv = $this->actingAs($exporter)->post(route('audit.export'), [$filter => $value])->streamedContent();

            $this->assertStringContainsString('Kept entry', $csv, $filter);
            $this->assertStringNotContainsString('Other entry', $csv, $filter);
        }
    }

    public function test_the_export_form_carries_the_filters_on_the_screen(): void
    {
        $person = User::factory()->create();

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->withQueryParams(['user' => (string) $person->getKey(), 'session' => self::TRAIL])
            ->test(ListAuditEntries::class)
            ->set('tableFilters.category.value', 'auth')
            ->set('tableFilters.event.value', 'auth.login')
            ->set('tableFilters.actor_type.value', AuditActor::GUEST)
            ->set('tableFilters.date.value', '2026-03-10')
            ->set('tableSearch', 'Kept')
            ->assertSeeHtml('name="category" value="auth"')
            ->assertSeeHtml('name="event" value="auth.login"')
            ->assertSeeHtml('name="actor_type" value="'.AuditActor::GUEST.'"')
            ->assertSeeHtml('name="date" value="2026-03-10"')
            ->assertSeeHtml('name="search" value="Kept"')
            ->assertSeeHtml('name="user" value="'.$person->getKey().'"')
            ->assertSeeHtml('name="session" value="'.self::TRAIL.'"');
    }

    public function test_one_session_lists_only_its_entries_in_the_order_they_happened(): void
    {
        $admin = User::factory()->superAdmin()->create();
        AuditEntry::factory()->create(['description' => 'Second step', 'session_trail' => self::TRAIL, 'created_at' => now()->subHour()]);
        AuditEntry::factory()->create(['description' => 'First step', 'session_trail' => self::TRAIL, 'created_at' => now()->subHours(2)]);
        AuditEntry::factory()->create(['description' => 'Third step', 'session_trail' => self::TRAIL, 'created_at' => now()->subMinute()]);
        AuditEntry::factory()->create(['description' => 'Another session', 'session_trail' => (string) Str::ulid()]);
        AuditEntry::factory()->create(['description' => 'No session']);

        // A trail typed or pasted in lower case is the same trail.
        $this->actingAs($admin)->get(route('audit.index', ['session' => strtolower(self::TRAIL)]))
            ->assertOk()
            ->assertSee(__('audit.showing_session'))
            ->assertSeeInOrder(['First step', 'Second step', 'Third step'])
            ->assertDontSee('Another session')
            ->assertDontSee('No session');

        $this->actingAs($admin)->get(route('audit.index', ['session' => 'not-a-trail']))
            ->assertOk()
            ->assertDontSee(__('audit.showing_session'))
            ->assertSeeInOrder(['Third step', 'Second step', 'First step'])
            ->assertSee('Another session')
            ->assertSee('No session');
    }

    public function test_each_banner_clears_only_its_own_filter(): void
    {
        $person = User::factory()->create();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('audit.index', ['user' => $person->getKey(), 'session' => self::TRAIL]))
            ->assertSee('href="'.e(route('audit.index', ['user' => $person->getKey()])).'"', false)
            ->assertSee('href="'.e(route('audit.index', ['session' => self::TRAIL])).'"', false);
    }

    public function test_an_entry_in_a_session_offers_view_session_and_one_outside_does_not(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $inSession = AuditEntry::factory()->create(['session_trail' => self::TRAIL]);
        $outside = AuditEntry::factory()->create();

        $this->actingAs($admin)->get(route('audit.show', $inSession))
            ->assertSee(__('audit.view_session'))
            ->assertSee('href="'.e(route('audit.index', ['session' => self::TRAIL])).'"', false);

        $this->actingAs($admin)->get(route('audit.show', $outside))
            ->assertDontSee(__('audit.view_session'))
            ->assertDontSee('session=', false);
    }

    public function test_a_row_in_a_session_offers_the_rest_of_it_until_the_list_is_that_session(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $inSession = AuditEntry::factory()->create(['session_trail' => self::TRAIL]);
        $outside = AuditEntry::factory()->create();
        $sessionUrl = route('audit.index', ['session' => self::TRAIL]);

        Livewire::actingAs($admin)
            ->test(ListAuditEntries::class)
            ->assertTableActionVisible('session', $inSession)
            ->assertTableActionHasUrl('session', $sessionUrl, $inSession)
            ->assertTableActionHidden('session', $outside);

        Livewire::actingAs($admin)
            ->withQueryParams(['session' => self::TRAIL])
            ->test(ListAuditEntries::class)
            ->assertTableActionHidden('session', $inSession);
    }

    public function test_the_context_takes_the_page_in_columns_only_when_there_is_nothing_else(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $alone = AuditEntry::factory()->create(['user_agent' => null, 'properties' => [], 'attribute_changes' => []]);
        $withDetails = AuditEntry::factory()->create(['user_agent' => 'Mozilla/5.0']);

        $threeColumns = '--cols-lg: repeat(3, minmax(0, 1fr))';

        $this->actingAs($admin)->get(route('audit.show', $alone))
            ->assertSee('lg:col-span-3', false)
            ->assertSee($threeColumns, false);

        $this->actingAs($admin)->get(route('audit.show', $withDetails))
            ->assertSee(__('audit.request_details'))
            ->assertDontSee('lg:col-span-3', false)
            ->assertDontSee($threeColumns, false);
    }

    public function test_one_session_exports_oldest_first_across_chunks_with_its_trail(): void
    {
        $exporter = User::factory()->withPermission(SystemPermission::EXPORT_AUDIT_LOG)->create();
        $start = now()->startOfMinute();
        $row = fn (string $description, ?string $trail, int $minutes): array => [
            'log_name' => 'data',
            'description' => $description,
            'actor_type' => AuditActor::SYSTEM,
            'session_trail' => $trail,
            'created_at' => $start->copy()->addMinutes($minutes),
            'updated_at' => $start,
        ];
        // Times run against id order, in threes that share a time, one three straddling the
        // 1,000-row chunk: only when, then id, puts them in order across the chunks.
        AuditEntry::query()->insert([
            ...array_fill(0, 5, $row('Another session', (string) Str::ulid(), 0)),
            ...array_map(fn (int $i): array => $row('In the session', self::TRAIL, intdiv(1004 - $i, 3)), range(0, 1004)),
        ]);
        $expected = AuditEntry::query()->where('session_trail', self::TRAIL)->orderBy('created_at')->orderBy('id')->pluck('id')->all();

        $rows = $this->exportRows($this->actingAs($exporter)->post(route('audit.export'), ['session' => self::TRAIL])->streamedContent());
        $ids = $rows->pluck('id')->map(fn (string $id): int => (int) $id)->all();

        $this->assertSame(['In the session'], $rows->pluck('description')->unique()->values()->all());
        $this->assertSame([self::TRAIL], $rows->pluck('session')->unique()->values()->all());
        $this->assertNotSame(collect($expected)->sort()->values()->all(), $expected, 'the times must disagree with id order');
        $this->assertSame($expected, $ids);
    }

    public function test_a_search_of_several_words_finds_the_same_rows_on_the_screen_and_in_the_export(): void
    {
        $exporter = User::factory()->superAdmin()->create();
        AuditEntry::factory()->create(['description' => 'Has alpha bravo together']);
        AuditEntry::factory()->create(['description' => 'Has bravo then alpha apart']);

        Livewire::actingAs($exporter)->test(ListAuditEntries::class)
            ->set('tableSearch', 'alpha bravo')
            ->assertSee('Has alpha bravo together')
            ->assertDontSee('Has bravo then alpha apart');

        $csv = $this->actingAs($exporter)->post(route('audit.export'), ['search' => 'alpha bravo'])->streamedContent();

        $this->assertStringContainsString('Has alpha bravo together', $csv);
        $this->assertStringNotContainsString('Has bravo then alpha apart', $csv);
    }

    public function test_the_export_includes_what_changed_and_the_other_properties(): void
    {
        $exporter = User::factory()->withPermission(SystemPermission::EXPORT_AUDIT_LOG)->create();
        $user = User::factory()->create(['first_name' => 'Before']);
        $user->update(['first_name' => 'After']);
        Audit::record('note.added', $user, properties: ['reason' => 'A good one']);

        $rows = $this->exportRows($this->actingAs($exporter)->post(route('audit.export'))->streamedContent());
        $updated = $rows->firstWhere('event', 'user.updated');
        $noted = $rows->firstWhere('event', 'note.added');

        $this->assertSame('After', json_decode($updated['changes'], true)['attributes']['first_name']);
        $this->assertSame('Before', json_decode($updated['changes'], true)['old']['first_name']);
        $this->assertSame('A good one', json_decode($noted['properties'], true)['reason']);
    }

    public function test_the_export_tells_a_spreadsheet_its_encoding_and_has_no_time_limit(): void
    {
        $exporter = User::factory()->withPermission(SystemPermission::EXPORT_AUDIT_LOG)->create();
        $previous = ini_get('max_execution_time');
        ini_set('max_execution_time', '30');

        try {
            $csv = $this->actingAs($exporter)->post(route('audit.export'))->streamedContent();

            $this->assertSame('0', ini_get('max_execution_time'));
        } finally {
            ini_set('max_execution_time', (string) $previous);
        }

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
    }

    public function test_the_export_neutralises_a_formula_a_visitor_could_have_typed(): void
    {
        $exporter = User::factory()->withPermission(SystemPermission::EXPORT_AUDIT_LOG)->create();
        $values = ['=1+1', '+1+1', '-1+1', '@SUM(A1)', "\t=1+1", "\r=1+1", ' =1+1', "\u{00A0}=1+1", "\u{200B}=1+1"];

        foreach ($values as $number => $value) {
            AuditEntry::factory()->create(['description' => $value, 'event' => "formula.{$number}"]);
        }

        $rows = $this->exportRows($this->actingAs($exporter)->post(route('audit.export'))->streamedContent())
            ->filter(fn (array $row): bool => str_starts_with($row['event'], 'formula.'));

        $this->assertCount(count($values), $rows);

        foreach ($rows as $row) {
            $this->assertStringStartsWith("'", $row['description']);
        }
    }

    public function test_the_export_covers_more_than_one_chunk_newest_first(): void
    {
        $exporter = User::factory()->withPermission(SystemPermission::EXPORT_AUDIT_LOG)->create();
        $person = User::factory()->create();
        $row = fn (string $description, User $about): array => [
            'log_name' => 'data',
            'description' => $description,
            'actor_type' => AuditActor::SYSTEM,
            'subject_type' => $about->getMorphClass(),
            'subject_id' => $about->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        // Someone else's rows, older by id: each chunk's start must not widen the person filter.
        AuditEntry::query()->insert([
            ...array_fill(0, 5, $row('Someone else', $exporter)),
            ...array_fill(0, 1005, $row('Bulk entry', $person)),
        ]);

        $rows = $this->exportRows($this->actingAs($exporter)->post(route('audit.export'), ['user' => (string) $person->getKey()])->streamedContent());
        $ids = $rows->where('description', 'Bulk entry')->pluck('id')->map(fn (string $id): int => (int) $id);

        $this->assertNotContains('Someone else', $rows->pluck('description'));
        $this->assertCount(1005, $ids);
        $this->assertSame($ids->sortDesc()->values()->all(), $ids->values()->all());
    }

    public function test_the_export_puts_a_backdated_entry_where_its_date_does_as_the_screen_does(): void
    {
        $this->actingAs(User::factory()->withPermission(SystemPermission::EXPORT_AUDIT_LOG)->create());
        Audit::record('note.added', description: 'Recent entry');
        Audit::record('note.imported', description: 'Imported entry', at: now()->subYear());
        Audit::record('note.added', description: 'Latest entry', at: now()->addMinute());

        $descriptions = $this->exportRows($this->post(route('audit.export'))->streamedContent())
            ->pluck('description')
            ->filter(fn (string $description): bool => str_ends_with($description, ' entry'))
            ->values()
            ->all();

        $this->assertSame(['Latest entry', 'Recent entry', 'Imported entry'], $descriptions);
    }

    public function test_the_export_refuses_a_filter_that_isnt_text_but_not_a_long_search(): void
    {
        $exporter = User::factory()->withPermission(SystemPermission::EXPORT_AUDIT_LOG)->create();

        $this->actingAs($exporter)->post(route('audit.export'), ['category' => ['a', 'b']])->assertSessionHasErrors('category');

        foreach (['nonsense', '1.5', '-5', '0', '99999999999999999999'] as $user) {
            $this->actingAs($exporter)->post(route('audit.export'), ['user' => $user])->assertSessionHasErrors('user');
        }

        $this->actingAs($exporter)->post(route('audit.export'), ['session' => 'not-a-trail'])->assertSessionHasErrors('session');

        $this->actingAs($exporter)->post(route('audit.export'), ['search' => str_repeat('a', 300)])->assertOk();
    }

    public function test_the_activity_panel_shows_only_to_someone_who_may_read_the_log(): void
    {
        $person = User::factory()->create();
        Audit::record('note.added', $person, description: 'A recent note');
        Audit::record('note.added', User::factory()->create(), description: 'About someone else');

        $allowed = User::factory()->withPermission(SystemPermission::VIEW_USERS, SystemPermission::VIEW_AUDIT_LOG)->create();
        $refused = User::factory()->withPermission(SystemPermission::VIEW_USERS)->create();

        Livewire::actingAs($allowed)->test(ShowUser::class, ['user' => $person])
            ->assertSee('A recent note')
            ->assertDontSee('About someone else')
            ->assertSeeHtml(route('audit.index', ['user' => $person->getKey()]));

        Livewire::actingAs($refused)->test(ShowUser::class, ['user' => $person])
            ->assertDontSee('A recent note')
            ->assertDontSee(__('audit.recent_activity'));
    }

    public function test_the_activity_panel_holds_the_latest_five(): void
    {
        $viewer = User::factory()->superAdmin()->create();
        $person = User::factory()->create();

        foreach (range(1, 6) as $number) {
            Audit::record("note.{$number}", $person, description: "Note number {$number}", at: now()->addMinutes($number));
        }

        Livewire::actingAs($viewer)->test(ShowUser::class, ['user' => $person])
            ->assertSee('Note number 6')
            ->assertSee('Note number 2')
            ->assertDontSee('Note number 1');
    }

    public function test_the_nav_item_is_in_the_system_group_and_follows_the_permission(): void
    {
        $viewer = User::factory()->withPermission(SystemPermission::VIEW_AUDIT_LOG)->create();
        $other = User::factory()->withPermission(SystemPermission::VIEW_USERS)->create();

        $this->assertTrue(NavRegistry::resolve($viewer)->contains(fn (object $node): bool => $node instanceof NavGroup && $node->name === 'system'));
        $this->assertFalse(NavRegistry::resolve($other)->contains(fn (object $node): bool => $node instanceof NavGroup && $node->name === 'system'));

        $this->actingAs($viewer)->get(route('dashboard'))->assertSee(__('Audit log'))->assertSee(__('System'));
        $this->actingAs($other)->get(route('dashboard'))->assertDontSee(__('Audit log'));
    }

    public function test_the_demo_seeder_gives_the_screen_something_to_show(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertTrue(AuditEntry::query()->where('event', 'impersonation.started')->exists());
        $this->assertTrue(AuditEntry::query()->where('event', 'schedule.task_failed')->exists());

        Livewire::actingAs(User::query()->where('is_super_admin', true)->firstOrFail())
            ->test(ListAuditEntries::class)
            ->assertOk();
    }

    public function test_switching_it_off_removes_the_route_nav_item_and_panel(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $hasPanel = fn (): bool => PanelRegistry::showPanels('users.show', $superAdmin, $superAdmin)->contains(fn (object $panel): bool => $panel instanceof ActivityPanel);
        $this->assertTrue($hasPanel());

        // Paths that don't exist, so a cached config or route file on this machine can't answer instead.
        $settings = [
            'AUDIT_ENABLED' => 'false',
            'APP_CONFIG_CACHE' => 'bootstrap/cache/audit-off-config.php',
            'APP_ROUTES_CACHE' => 'bootstrap/cache/audit-off-routes.php',
        ];
        $previous = array_map(fn (string $name): string|false => getenv($name), array_combine(array_keys($settings), array_keys($settings)));

        foreach ($settings as $name => $value) {
            putenv("{$name}={$value}");
            $_ENV[$name] = $_SERVER[$name] = $value;
        }

        NavRegistry::flush();
        PanelRegistry::flush();

        try {
            $app = require base_path('bootstrap/app.php');
            $app->make(Kernel::class)->bootstrap();

            $this->assertFalse($app['config']->get('activitylog.enabled'));
            $this->assertFalse($app['router']->has('audit.index'));
            $this->assertFalse($app['router']->has('audit.export'));
            $this->assertSame([], NavRegistry::group('system')->items);
            $this->assertFalse($hasPanel());
        } finally {
            foreach ($previous as $name => $value) {
                $value === false ? putenv($name) : putenv("{$name}={$value}");
                unset($_ENV[$name], $_SERVER[$name]);
            }

            Application::setInstance($this->app);
            Facade::setFacadeApplication($this->app);
            Facade::clearResolvedInstances();
        }
    }

    private function queriesWhileRefreshing(Testable $page): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $page->call('$refresh');

        $count = count(DB::getQueryLog());

        DB::disableQueryLog();

        return $count;
    }

    /**
     * The CSV as rows keyed by heading, without the byte order mark.
     *
     * @return Collection<int, array<string, string>>
     */
    private function exportRows(string $csv): Collection
    {
        $file = fopen('php://memory', 'r+');
        fwrite($file, Str::after($csv, "\xEF\xBB\xBF"));
        rewind($file);

        $headings = fgetcsv($file, escape: '');
        $rows = collect();

        while (($row = fgetcsv($file, escape: '')) !== false) {
            $rows->push(array_combine($headings, $row));
        }

        fclose($file);

        return $rows;
    }
}
