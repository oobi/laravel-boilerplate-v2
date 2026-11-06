<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Enums\SystemPermission;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\AuditEntry;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Audit\AuditActor;
use App\Support\Audit\AuditCategory;
use App\Support\Audit\LogAuditEntry;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuditRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_an_event_described_in_words_by_default(): void
    {
        $subject = User::factory()->create();

        Audit::record('invoice_batch.sent', $subject, ['count' => 3], category: AuditCategory::SYSTEM);

        $entry = $this->recorded();

        $this->assertSame('invoice_batch.sent', $entry->event);
        $this->assertSame('Invoice batch sent', $entry->description);
        $this->assertSame(AuditCategory::SYSTEM->value, $entry->log_name);
        $this->assertTrue($entry->subject->is($subject));
        $this->assertSame(3, $entry->getProperty('count'));
    }

    public function test_a_project_can_log_under_its_own_category(): void
    {
        Audit::record('item.raised', category: 'ledger');

        $this->assertSame('ledger', $this->recorded()->log_name);
    }

    public function test_an_entry_can_be_backdated_and_attributed_for_an_import(): void
    {
        $author = User::factory()->create();
        $at = CarbonImmutable::parse('2024-03-01 09:00:00');

        Audit::record('item.imported', by: $author, at: $at);

        $entry = $this->recorded();

        $this->assertTrue($entry->created_at->equalTo($at));
        $this->assertTrue($entry->causer->is($author));
        $this->assertSame($author->name, $entry->actor_name);
    }

    public function test_sensitive_properties_are_redacted_at_any_depth(): void
    {
        Audit::record('invoice.sent', properties: ['to' => 'a@example.test', 'mail' => ['smtp_password' => 'secret-value']]);

        $this->assertStringNotContainsString('secret-value', DB::table('activity_log')->value('properties'));
        $this->assertSame(LogAuditEntry::REDACTED, $this->recorded()->getProperty('mail.smtp_password'));
        $this->assertSame('a@example.test', $this->recorded()->getProperty('to'));
    }

    #[DataProvider('sensitiveNames')]
    public function test_sensitive_names_are_recognised_whatever_their_case_or_separators(string $name): void
    {
        Audit::record('invoice.sent', properties: [$name => 'secret-value']);

        $this->assertStringNotContainsString('secret-value', DB::table('activity_log')->value('properties'));
    }

    /** @return array<string, array{string}> */
    public static function sensitiveNames(): array
    {
        return collect([
            'apiKey', 'api-key', 'api.key', 'api key', 'X-Api-Key', 'privateKey', 'recoveryCodes', 'PASSWORD', 'passwd', 'passcode',
            'pass_phrase', 'pwd', 'Authorization', 'Proxy-Authorization', 'Set-Cookie', 'cookies', 'aws_credentials',
            'access_key', 'encryption_key', 'signing_key', 'bearer', 'jwt', 'totp_code', 'session_id', 'authorization_header',
        ])
            ->mapWithKeys(fn (string $name): array => [$name => [$name]])
            ->all();
    }

    public function test_ordinary_names_are_left_readable(): void
    {
        Audit::record('invoice.sent', properties: ['cookie_consent' => 'yes', 'authorization_status' => 'approved', 'name' => 'Sprocket']);

        $entry = $this->recorded();

        $this->assertSame('yes', $entry->getProperty('cookie_consent'));
        $this->assertSame('approved', $entry->getProperty('authorization_status'));
        $this->assertSame('Sprocket', $entry->getProperty('name'));
    }

    public function test_a_project_pattern_matches_whatever_its_case_or_separators(): void
    {
        config(['audit.redact' => ['*NATIONAL_ID*']]);

        Audit::record('invoice.sent', properties: ['nationalId' => 'secret-value']);

        $this->assertSame(LogAuditEntry::REDACTED, $this->recorded()->getProperty('nationalId'));
    }

    public function test_a_sensitive_name_inside_a_collection_property_is_redacted(): void
    {
        Audit::record('invoice.sent', properties: ['mail' => collect(['password' => 'secret-value'])]);

        $this->assertStringNotContainsString('secret-value', DB::table('activity_log')->value('properties'));
    }

    public function test_a_direct_activity_call_cannot_store_a_sensitive_change_at_the_top_level(): void
    {
        activity()->withChanges(['password' => 'secret-value', 'nested_secret' => ['a' => 'secret-value']])->log('Changed');

        $this->assertStringNotContainsString('secret-value', DB::table('activity_log')->value('attribute_changes'));
    }

    public function test_malformed_text_cannot_stop_an_entry(): void
    {
        Audit::record('login.failed', properties: ['email' => "bad\xFFinput", 'nested' => ['value' => "bad\xFFinput"], "key\xFF" => 'kept'], description: "Failed for bad\xFFinput");

        $entry = $this->recorded();

        $this->assertStringStartsWith('bad', $entry->getProperty('email'));
        $this->assertStringStartsWith('bad', $entry->getProperty('nested.value'));
        $this->assertStringStartsWith('Failed for bad', $entry->description);
    }

    public function test_malformed_text_inside_an_object_cannot_stop_an_entry(): void
    {
        Audit::record('login.failed', properties: [
            'collection' => collect(['value' => "bad\xFFinput"]),
            'object' => (object) ['value' => "bad\xFFinput"],
            'count' => 3,
        ]);

        $entry = $this->recorded();

        $this->assertStringStartsWith('bad', $entry->getProperty('collection.value'));
        $this->assertStringStartsWith('bad', $entry->getProperty('object.value'));
        $this->assertSame(3, $entry->getProperty('count'));
    }

    public function test_a_name_too_long_for_its_column_is_cut_and_does_not_stop_the_entry(): void
    {
        $this->auditRoute();
        $admin = User::factory()->create(['first_name' => str_repeat('a', 200), 'last_name' => str_repeat('b', 200)]);
        $target = User::factory()->create(['first_name' => str_repeat('c', 200), 'last_name' => str_repeat('d', 200)]);

        $this->actingAs($target)
            ->withSession([config('laravel-impersonate.session_key') => $admin->getKey()])
            ->post('/audit-test')
            ->assertOk();

        $entry = $this->recorded();

        $this->assertSame(255, mb_strlen($entry->actor_name));
        $this->assertSame(255, mb_strlen($entry->impersonator_name));
    }

    public function test_a_named_actor_too_long_for_its_column_is_cut(): void
    {
        Audit::as('crm', fn () => Audit::record('contact.synced'), str_repeat('a', 300));

        $this->assertSame(255, mb_strlen($this->recorded()->actor_name));
    }

    public function test_a_long_description_is_cut(): void
    {
        config(['audit.max_value_length' => 10]);

        Audit::record('invoice.sent', description: str_repeat('a', 50));

        $this->assertSame(str_repeat('a', 10).'...', $this->recorded()->description);
    }

    public function test_a_description_is_stored_as_written_so_placeholders_cannot_reveal_attributes(): void
    {
        $user = User::factory()->create(['password' => 'correct-horse']);
        $this->actingAs($user);

        Audit::record('user.renamed', $user, description: 'Renamed to :causer.password :subject.password :properties.x');

        $this->assertSame('Renamed to :causer.password :subject.password :properties.x', $this->recorded()->description);
    }

    public function test_a_direct_activity_call_gets_the_same_protection(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        activity()->withProperties(['password' => 'secret-value'])->log('Did :causer.password');

        $entry = $this->recorded();

        $this->assertSame('Did :causer.password', $entry->description);
        $this->assertSame(LogAuditEntry::REDACTED, $entry->getProperty('password'));
        $this->assertSame(AuditActor::USER, $entry->actor_type);
    }

    public function test_the_signed_in_user_is_the_actor_and_their_name_outlives_them(): void
    {
        $user = User::factory()->create();
        $name = $user->name;
        $this->actingAs($user);

        Audit::record('invoice.sent');
        Auth::logout();
        $user->forceDelete();

        $entry = $this->recorded();

        $this->assertSame(AuditActor::USER, $entry->actor_type);
        $this->assertSame($name, $entry->actor_name);
    }

    public function test_outside_a_request_with_nobody_signed_in_the_actor_is_the_system(): void
    {
        Audit::record('invoice.sent');

        $entry = $this->recorded();

        $this->assertSame(AuditActor::SYSTEM, $entry->actor_type);
        $this->assertNull($entry->causer_id);
        $this->assertNull($entry->ip_address);
    }

    public function test_a_block_can_run_as_a_named_actor_and_the_actor_ends_with_it(): void
    {
        Audit::as('crm', fn () => Audit::record('contact.synced'), 'HubSpot');
        Audit::record('invoice.sent');

        $entries = AuditEntry::query()->orderBy('id')->get();

        $this->assertSame('crm', $entries[0]->actor_type);
        $this->assertSame('HubSpot', $entries[0]->actor_name);
        $this->assertSame(AuditActor::SYSTEM, $entries[1]->actor_type);
        $this->assertNull($entries[1]->actor_name);
    }

    public function test_a_block_can_name_an_actor_type_for_a_signed_in_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Audit::as('claude', fn () => Audit::record('note.drafted'));

        $entry = $this->recorded();

        $this->assertSame('claude', $entry->actor_type);
        $this->assertTrue($entry->causer->is($user));
        $this->assertSame($user->name, $entry->actor_name);
    }

    public function test_a_web_request_records_where_it_came_from(): void
    {
        $this->auditRoute();

        $this->withHeader('User-Agent', 'TestBrowser/1.0')->post('/audit-test')->assertOk();

        $entry = $this->recorded();

        $this->assertSame(AuditActor::GUEST, $entry->actor_type);
        $this->assertSame('127.0.0.1', $entry->ip_address);
        $this->assertSame('TestBrowser/1.0', $entry->user_agent);
    }

    public function test_a_long_user_agent_is_cut_to_fit_its_column(): void
    {
        $this->auditRoute();

        $this->withHeader('User-Agent', str_repeat('a', 600))->post('/audit-test')->assertOk();

        $this->assertSame(str_repeat('a', 512), $this->recorded()->user_agent);
    }

    public function test_an_empty_user_agent_is_stored_as_nothing(): void
    {
        $this->auditRoute();

        $this->withHeader('User-Agent', '')->post('/audit-test')->assertOk();

        $this->assertNull($this->recorded()->user_agent);
    }

    public function test_a_malformed_user_agent_cannot_stop_the_entry(): void
    {
        $this->auditRoute();

        $this->withHeader('User-Agent', "Bad\xFFBrowser")->post('/audit-test')->assertOk();

        $this->assertStringStartsWith('Bad', $this->recorded()->user_agent);
        $this->assertTrue(mb_check_encoding($this->recorded()->user_agent, 'UTF-8'));
    }

    public function test_an_impersonator_who_was_soft_deleted_is_still_named(): void
    {
        $this->auditRoute();
        $admin = User::factory()->create();
        $target = User::factory()->create();
        $admin->delete();

        $this->withoutMiddleware(EnsureAccountIsActive::class)->actingAs($target)
            ->withSession([config('laravel-impersonate.session_key') => $admin->getKey()])
            ->post('/audit-test')
            ->assertOk();

        $entry = $this->recorded();

        $this->assertSame($admin->getKey(), $entry->impersonator_id);
        $this->assertTrue($entry->impersonator->is($admin));
    }

    public function test_permanently_deleting_an_impersonator_keeps_their_name_on_the_entry(): void
    {
        $this->auditRoute();
        $admin = User::factory()->create();
        $target = User::factory()->create();
        $name = $admin->name;

        $this->actingAs($target)
            ->withSession([config('laravel-impersonate.session_key') => $admin->getKey()])
            ->post('/audit-test')
            ->assertOk();

        $admin->forceDelete();

        $entry = $this->recorded();

        $this->assertNull($entry->impersonator_id);
        $this->assertSame($name, $entry->impersonator_name);
    }

    public function test_a_real_queued_job_is_attributed_to_the_person_who_dispatched_it(): void
    {
        config(['queue.default' => 'database']);
        $user = User::factory()->create();
        $this->actingAs($user);

        dispatch(function (): void {
            Audit::record('report.generated');
        });

        Auth::logout();
        Context::flush();

        $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true])->assertSuccessful();

        $entry = $this->recorded();

        $this->assertSame(AuditActor::QUEUE, $entry->actor_type);
        $this->assertTrue($entry->causer->is($user));
    }

    public function test_a_dispatcher_left_in_context_is_ignored_in_a_web_request(): void
    {
        $this->auditRoute();
        $dispatcher = User::factory()->create();
        Context::addHidden(AuditActor::CONTEXT_DISPATCHED_BY, $dispatcher->getKey());

        $this->post('/audit-test')->assertOk();

        $entry = $this->recorded();

        $this->assertSame(AuditActor::GUEST, $entry->actor_type);
        $this->assertNull($entry->causer_id);
    }

    public function test_an_act_while_impersonating_names_both_the_impersonated_user_and_the_real_admin(): void
    {
        $this->auditRoute();
        $admin = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($target)
            ->withSession([config('laravel-impersonate.session_key') => $admin->getKey()])
            ->post('/audit-test')
            ->assertOk();

        $entry = $this->recorded();

        $this->assertTrue($entry->causer->is($target));
        $this->assertSame($target->name, $entry->actor_name);
        $this->assertSame($admin->getKey(), $entry->impersonator_id);
        $this->assertSame($admin->name, $entry->impersonator_name);
    }

    public function test_an_impersonator_who_no_longer_exists_does_not_stop_the_entry(): void
    {
        $this->auditRoute();
        $admin = User::factory()->create();
        $target = User::factory()->create();
        $adminId = $admin->getKey();
        $admin->forceDelete();

        // That middleware ends such a session on the web group; a route outside it still logs.
        $this->withoutMiddleware(EnsureAccountIsActive::class)->actingAs($target)
            ->withSession([config('laravel-impersonate.session_key') => $adminId])
            ->post('/audit-test')
            ->assertOk();

        $this->assertNull($this->recorded()->impersonator_id);
    }

    public function test_a_queued_job_is_attributed_to_the_person_who_dispatched_it(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $dispatchedContext = Context::dehydrate();

        Auth::logout();
        Context::flush();
        Context::hydrate($dispatchedContext);

        Audit::record('report.generated');

        $entry = $this->recorded();

        $this->assertSame(AuditActor::QUEUE, $entry->actor_type);
        $this->assertTrue($entry->causer->is($user));
        $this->assertSame($user->name, $entry->actor_name);
    }

    public function test_a_job_whose_dispatcher_no_longer_exists_is_still_recorded(): void
    {
        Context::addHidden(AuditActor::CONTEXT_DISPATCHED_BY, 999999);

        Audit::record('report.generated');

        $entry = $this->recorded();

        $this->assertSame(AuditActor::QUEUE, $entry->actor_type);
        $this->assertNull($entry->causer_id);
        $this->assertNull($entry->actor_name);
    }

    public function test_a_job_dispatched_by_a_job_keeps_the_original_person(): void
    {
        $original = User::factory()->create();
        $other = User::factory()->create();
        Context::addHidden(AuditActor::CONTEXT_DISPATCHED_BY, $original->getKey());
        $this->actingAs($other);

        $dispatchedContext = Context::dehydrate();

        Auth::logout();
        Context::flush();
        Context::hydrate($dispatchedContext);

        Audit::record('report.generated');

        $this->assertTrue($this->recorded()->causer->is($original));
    }

    public function test_a_soft_deleted_dispatcher_is_still_named(): void
    {
        $user = User::factory()->create();
        $user->delete();
        Context::addHidden(AuditActor::CONTEXT_DISPATCHED_BY, $user->getKey());

        Audit::record('report.generated');

        $entry = $this->recorded();

        $this->assertSame($user->getKey(), $entry->causer_id);
        $this->assertSame($user->name, $entry->actor_name);
    }

    public function test_a_dispatcher_does_not_override_the_signed_in_user(): void
    {
        $dispatcher = User::factory()->create();
        $user = User::factory()->create();
        Context::addHidden(AuditActor::CONTEXT_DISPATCHED_BY, $dispatcher->getKey());
        $this->actingAs($user);

        Audit::record('report.generated');

        $entry = $this->recorded();

        $this->assertTrue($entry->causer->is($user));
        $this->assertSame(AuditActor::USER, $entry->actor_type);
    }

    public function test_a_job_dispatched_while_impersonating_names_the_real_admin(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create();
        session([config('laravel-impersonate.session_key') => $admin->getKey()]);
        $this->actingAs($target);

        $dispatchedContext = Context::dehydrate();

        Auth::logout();
        session()->flush();
        Context::flush();
        Context::hydrate($dispatchedContext);

        Audit::record('report.generated');

        $entry = $this->recorded();

        $this->assertTrue($entry->causer->is($target));
        $this->assertSame($admin->getKey(), $entry->impersonator_id);
        $this->assertSame($admin->name, $entry->impersonator_name);
    }

    /** @param  Closure(AuditEntry): mixed  $tamper */
    #[DataProvider('waysToChangeAnEntry')]
    public function test_an_entry_cannot_be_changed_through_the_model(Closure $tamper): void
    {
        $entry = AuditEntry::factory()->create();

        try {
            $tamper($entry);
            $this->fail('An audit entry was changed.');
        } catch (LogicException) {
            $this->assertSame('Record updated', $entry->fresh()->description);
        }
    }

    /** @return array<string, array{Closure(AuditEntry): mixed}> */
    public static function waysToChangeAnEntry(): array
    {
        return [
            'update' => [fn (AuditEntry $entry) => $entry->update(['description' => 'Tampered'])],
            'updateQuietly' => [fn (AuditEntry $entry) => $entry->updateQuietly(['description' => 'Tampered'])],
            'saveQuietly' => [fn (AuditEntry $entry) => $entry->fill(['description' => 'Tampered'])->saveQuietly()],
            'withoutEvents' => [fn (AuditEntry $entry) => AuditEntry::withoutEvents(fn () => $entry->update(['description' => 'Tampered']))],
            'increment' => [fn (AuditEntry $entry) => $entry->increment('subject_id', 1, ['description' => 'Tampered'])],
            'decrement' => [fn (AuditEntry $entry) => $entry->decrement('subject_id', 1, ['description' => 'Tampered'])],
            'incrementQuietly' => [fn (AuditEntry $entry) => $entry->incrementQuietly('subject_id', 1, ['description' => 'Tampered'])],
        ];
    }

    /** @param  Closure(AuditEntry): mixed  $tamper */
    #[DataProvider('waysToDeleteAnEntry')]
    public function test_an_entry_cannot_be_deleted_through_the_model(Closure $tamper): void
    {
        $entry = AuditEntry::factory()->create();

        try {
            $tamper($entry);
            $this->fail('An audit entry was deleted.');
        } catch (LogicException) {
            $this->assertModelExists($entry);
        }
    }

    /** @return array<string, array{Closure(AuditEntry): mixed}> */
    public static function waysToDeleteAnEntry(): array
    {
        return [
            'delete' => [fn (AuditEntry $entry) => $entry->delete()],
            'deleteQuietly' => [fn (AuditEntry $entry) => $entry->deleteQuietly()],
            'withoutEvents' => [fn (AuditEntry $entry) => AuditEntry::withoutEvents(fn () => $entry->delete())],
        ];
    }

    public function test_the_retention_clean_removes_only_entries_past_their_age(): void
    {
        config(['activitylog.clean_after_days' => 30]);
        $old = AuditEntry::factory()->create(['created_at' => now()->subDays(31)]);
        $recent = AuditEntry::factory()->create(['created_at' => now()->subDays(29)]);

        $this->artisan('activitylog:clean', ['--force' => true])->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
    }

    public function test_export_carries_view_and_view_carries_panel_entry(): void
    {
        $exporter = User::factory()->withPermission(SystemPermission::EXPORT_AUDIT_LOG)->create();
        $viewer = User::factory()->withPermission(SystemPermission::VIEW_AUDIT_LOG)->create();

        $this->assertTrue($exporter->hasSystemPermission(SystemPermission::VIEW_AUDIT_LOG));
        $this->assertTrue($exporter->canAccessAdmin());
        $this->assertTrue($viewer->canAccessAdmin());
        $this->assertFalse($viewer->hasSystemPermission(SystemPermission::EXPORT_AUDIT_LOG));
    }

    /** The one entry the test made, leaving out those for the users it set up and signed out. */
    private function recorded(): AuditEntry
    {
        return AuditEntry::query()
            ->where(fn ($query) => $query->whereNull('event')->orWhereNotIn('event', ['user.created', 'user.deleted', 'user.force_deleted', 'auth.logout']))
            ->sole();
    }

    private function auditRoute(): void
    {
        Route::post('/audit-test', function () {
            Audit::record('invoice.sent');

            return response()->noContent(200);
        })->middleware('web');
    }
}
