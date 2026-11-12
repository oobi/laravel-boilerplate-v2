<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Livewire\Admin\Audit\ListAuditEntries;
use App\Livewire\Admin\Audit\ShowAuditEntry;
use App\Models\AuditEntry;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Audit\AuditActor;
use App\Support\Audit\AuditCategory;
use App\Support\Audit\AuditDisplay;
use App\Support\Audit\AuditView;
use App\Support\DisplayTimezone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/** The audit list as a feed: sentence rows, day headings, the display time zone and quick views. */
class AuditFeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 3 am on Thursday 12 November in UTC is 1 pm the same day in Brisbane.
        $this->travelTo(Carbon::parse('2026-11-12 03:00:00', 'UTC'));
        config(['app.display_timezone' => 'Australia/Brisbane']);
    }

    public function test_an_entry_reads_as_a_sentence_with_the_actor_and_subject_linked(): void
    {
        [$admin, $target] = Audit::withoutLogging(fn (): array => [
            User::factory()->superAdmin()->create(['first_name' => 'Super', 'last_name' => 'Admin', 'email' => 'admin@example.test']),
            User::factory()->create(['first_name' => 'Priya', 'last_name' => 'Patel', 'email' => 'priya@example.test']),
        ]);
        Audit::record('user.roles_changed', $target, by: $admin, category: AuditCategory::ACCESS);

        $this->list($admin)
            ->assertSeeHtml('<a href="'.route('users.show', $admin).'" class="link link-hover font-medium">Super Admin</a> changed the roles of <a href="'.route('users.show', $target).'" class="link link-hover">Priya Patel</a>')
            ->assertSee('admin@example.test → priya@example.test')
            // The sentence names both, so neither is repeated under it.
            ->assertDontSee(__('audit.by', ['actor' => 'Super Admin']))
            ->assertDontSee(__('audit.subject').':');
    }

    public function test_a_login_names_its_person_once(): void
    {
        $person = Audit::withoutLogging(fn (): User => User::factory()->create(['first_name' => 'Jo', 'last_name' => 'Smith', 'email' => 'jo@example.test']));
        Audit::record('auth.login', $person, by: $person, category: AuditCategory::AUTH);

        $html = $this->list($this->viewer())->assertSee('Jo Smith')->assertSee('logged in')->html();

        $this->assertSame(1, substr_count($html, 'jo@example.test'));
    }

    public function test_an_entry_without_a_sentence_says_who_did_it_and_what_it_applies_to(): void
    {
        $person = Audit::withoutLogging(fn (): User => User::factory()->create(['first_name' => 'Named', 'last_name' => 'Person']));
        Audit::as(AuditActor::QUEUE, fn () => Audit::record('parcel.weighed', $person, description: 'Parcel weighed'));

        $this->list($this->viewer())
            ->assertSee('Parcel weighed')
            ->assertSee(__('audit.by', ['actor' => 'Queue']))
            ->assertSeeHtml(e(__('audit.subject')).':')
            ->assertSee('Named Person');
    }

    public function test_a_failed_login_reads_as_a_sentence_with_the_address_typed(): void
    {
        AuditEntry::factory()->create([
            'event' => 'auth.login_failed',
            'description' => 'Failed login',
            'log_name' => 'auth',
            'actor_type' => AuditActor::GUEST,
            'actor_name' => null,
            'properties' => ['identifier' => 'nobody@example.test'],
        ]);

        $this->list($this->viewer())
            ->assertSeeHtml('<span class="font-medium">Visitor</span> failed to log in')
            ->assertSee('nobody@example.test');
    }

    public function test_entries_sit_under_their_day_in_the_display_time_zone(): void
    {
        // Late on the 11th in UTC: already the 12th in Brisbane.
        AuditEntry::factory()->create(['description' => 'Early today', 'created_at' => '2026-11-11 15:00:00']);
        AuditEntry::factory()->create(['description' => 'Yesterday entry', 'created_at' => '2026-11-11 01:00:00']);
        AuditEntry::factory()->create(['description' => 'Older entry', 'created_at' => '2026-11-02 01:00:00']);
        AuditEntry::factory()->create(['description' => 'Last year entry', 'created_at' => '2025-06-02 01:00:00']);

        $this->list($this->viewer())
            ->assertSeeInOrder([
                'Today, Thursday 12 November', 'Early today', '1:00 am',
                'Yesterday, Wednesday 11 November', 'Yesterday entry', '11:00 am',
                'Monday 2 November', 'Older entry',
                'Monday 2 June 2025', 'Last year entry',
            ]);
    }

    public function test_the_page_names_the_zone_its_times_are_in(): void
    {
        $this->list($this->viewer())->assertSee('Times in Brisbane time (AEST).');

        config(['app.display_timezone' => null]);

        $this->list($this->viewer())->assertSee('Times in UTC.');
    }

    public function test_the_date_filter_picks_days_in_the_display_time_zone(): void
    {
        AuditEntry::factory()->create(['description' => 'Kept entry', 'created_at' => '2026-03-09 15:00:00']);
        AuditEntry::factory()->create(['description' => 'Other entry', 'created_at' => '2026-03-10 15:00:00']);

        $this->list($this->viewer())
            ->set('tableFilters.date.value', '2026-03-10')
            ->assertSee('Kept entry')
            ->assertDontSee('Other entry');
    }

    public function test_the_detail_page_shows_the_time_in_the_display_time_zone(): void
    {
        $entry = AuditEntry::factory()->create(['created_at' => '2026-11-11 15:00:00']);

        Livewire::actingAs($this->viewer())
            ->test(ShowAuditEntry::class, ['entry' => $entry])
            ->assertSee('12 November 2026, 1:00 am AEST');
    }

    public function test_a_display_time_zone_that_isnt_one_is_refused(): void
    {
        config(['app.display_timezone' => 'Brisbane']);

        $this->expectException(InvalidArgumentException::class);

        DisplayTimezone::name();
    }

    public function test_a_zone_with_no_lettered_abbreviation_reads_as_an_offset(): void
    {
        config(['app.display_timezone' => 'Asia/Dubai']);

        $this->assertSame('UTC+4', DisplayTimezone::abbreviation(now()));
        $this->assertSame('Dubai time (UTC+4)', DisplayTimezone::label());
    }

    public function test_changes_leaves_out_logins_and_each_view_counts_its_rows(): void
    {
        $this->entries(['auth' => 'A login', 'data' => 'A record change', 'access' => 'A role change', 'security' => 'A password change', 'system' => 'A failed job']);

        $list = $this->list($this->viewer());

        $this->assertSame(
            ['all' => 5, 'changes' => 2, 'security' => 3, 'system' => 1],
            collect($list->instance()->quickViews())->mapWithKeys(fn (array $tab): array => [$tab['view']->value => $tab['count']])->all(),
        );

        $list->call('showView', 'changes')
            ->assertSet('activeView', 'changes')
            ->assertSee('A record change')
            ->assertSee('A role change')
            ->assertDontSee('A login')
            ->assertDontSee('A password change')
            ->assertDontSee('A failed job');
    }

    public function test_the_counts_follow_the_search(): void
    {
        $this->entries(['auth' => 'Kept login', 'data' => 'Other change']);

        $list = $this->list($this->viewer())->set('tableSearch', 'Kept');

        $this->assertSame([1, 0], [$this->viewCount($list, AuditView::ALL), $this->viewCount($list, AuditView::CHANGES)]);
    }

    public function test_the_email_view_shows_once_an_email_is_logged(): void
    {
        $this->entries(['auth' => 'A login']);

        $this->assertNotContains(AuditView::EMAIL, $this->views($this->list($this->viewer())));

        $this->entries([AuditView::EMAIL_CATEGORY => 'An email']);
        cache()->flush();

        $list = $this->list($this->viewer());

        $this->assertContains(AuditView::EMAIL, $this->views($list));
        $list->call('showView', 'email')->assertSee('An email')->assertDontSee('A login');
    }

    public function test_a_project_category_joins_the_views_it_names_and_otherwise_shows_under_all_only(): void
    {
        AuditDisplay::registerCategory('billing', 'Invoices and payments', [AuditView::CHANGES]);
        $this->entries(['billing' => 'An invoice', 'ledger' => 'A ledger line']);

        $list = $this->list($this->viewer());

        $this->assertSame([2, 1, 0], [$this->viewCount($list, AuditView::ALL), $this->viewCount($list, AuditView::CHANGES), $this->viewCount($list, AuditView::SECURITY)]);
        $list->call('showView', 'changes')->assertSee('An invoice')->assertDontSee('A ledger line');
    }

    public function test_a_view_the_url_cant_name_shows_everything(): void
    {
        $this->entries(['auth' => 'A login']);

        Livewire::actingAs($this->viewer())
            ->withQueryParams(['view' => 'nonsense'])
            ->test(ListAuditEntries::class)
            ->assertSet('activeView', 'all')
            ->assertSee('A login');
    }

    public function test_the_open_view_is_a_selected_tab_and_goes_to_the_export(): void
    {
        $this->list($this->viewer())
            ->call('showView', 'security')
            ->assertSeeHtml('aria-label="'.__('audit.quick_views').'"')
            // Each tab's aria-selected comes just before its id; the panel is named by the open one.
            ->assertSeeHtmlInOrder([
                'aria-selected="false"', 'id="audit-view-all"',
                'aria-selected="true"', 'id="audit-view-security"',
                'role="tabpanel" aria-labelledby="audit-view-security"',
            ])
            ->assertSeeHtml('name="view" value="security"');
    }

    /** A table that sets no page sizes of its own gets the app's, as every list does, not Filament's 10 of 5 to 50. */
    public function test_the_list_pages_by_the_shared_page_sizes(): void
    {
        $list = $this->list($this->viewer());

        $this->assertSame(config('pagination.page_sizes'), $list->instance()->getTable()->getPaginationPageOptions());
        $this->assertSame(config('pagination.default_page_size'), $list->instance()->getTableRecordsPerPage());
    }

    /** Made unlogged: creating a user writes an entry of its own, which would show and count. */
    private function viewer(): User
    {
        return Audit::withoutLogging(fn (): User => User::factory()->superAdmin()->create());
    }

    private function list(User $viewer): Testable
    {
        return Livewire::actingAs($viewer)->test(ListAuditEntries::class);
    }

    /** @param  array<string, string>  $byCategory  category => description */
    private function entries(array $byCategory): void
    {
        foreach ($byCategory as $category => $description) {
            AuditEntry::factory()->create(['log_name' => $category, 'description' => $description]);
        }
    }

    private function viewCount(Testable $list, AuditView $view): int
    {
        return collect($list->instance()->quickViews())->firstWhere('view', $view)['count'] ?? 0;
    }

    /** @return list<AuditView> */
    private function views(Testable $list): array
    {
        return collect($list->instance()->quickViews())->pluck('view')->all();
    }
}
