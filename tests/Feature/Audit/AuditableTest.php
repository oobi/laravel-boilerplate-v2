<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Models\AuditEntry;
use App\Support\Audit\Audit;
use App\Support\Audit\AuditCategory;
use App\Support\Audit\LogAuditEntry;
use Closure;
use Illuminate\Database\Eloquent\Casts\AsStringable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Activitylog\Actions\LogActivityAction;
use Spatie\Activitylog\Support\ActivityLogStatus;
use Tests\Fixtures\AuditedPivot;
use Tests\Fixtures\AuditedWidget;
use Tests\Fixtures\AuditedWidgetWithOwnWrites;
use Tests\Fixtures\AuditedWidgetWithVisible;
use Tests\TestCase;

class AuditableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        AuditedWidget::createTable();
    }

    public function test_creating_a_model_logs_its_attributes(): void
    {
        $widget = AuditedWidget::create(['name' => 'Sprocket']);

        $entry = AuditEntry::sole();

        $this->assertSame('audited_widget.created', $entry->event);
        $this->assertSame('Audited widget created', $entry->description);
        $this->assertSame(AuditCategory::DATA->value, $entry->log_name);
        $this->assertTrue($entry->subject->is($widget));
        $this->assertSame('Sprocket', $entry->attribute_changes['attributes']['name']);
    }

    public function test_updating_a_model_logs_only_what_changed_with_before_and_after(): void
    {
        $widget = AuditedWidget::create(['name' => 'Sprocket', 'body' => 'Unchanged']);

        $widget->update(['name' => 'Cog']);

        $entry = AuditEntry::query()->where('event', 'audited_widget.updated')->sole();

        $this->assertSame(['name' => 'Cog'], $entry->attribute_changes['attributes']);
        $this->assertSame(['name' => 'Sprocket'], $entry->attribute_changes['old']);
    }

    public function test_touching_a_model_logs_nothing(): void
    {
        $widget = AuditedWidget::create(['name' => 'Sprocket']);

        $this->travel(1)->minute();
        $widget->touch();

        $this->assertSame(1, AuditEntry::count());
    }

    public function test_deleting_and_restoring_a_model_are_logged(): void
    {
        $widget = AuditedWidget::create(['name' => 'Sprocket']);

        $widget->delete();
        $widget->restore();

        $this->assertSame(
            ['audited_widget.created', 'audited_widget.deleted', 'audited_widget.restored'],
            AuditEntry::query()->orderBy('id')->pluck('event')->all(),
        );
        $this->assertSame('Sprocket', AuditEntry::query()->where('event', 'audited_widget.deleted')->sole()->attribute_changes['old']['name']);
    }

    public function test_a_soft_deleted_subject_is_still_reachable_from_its_entry(): void
    {
        $widget = AuditedWidget::create(['name' => 'Sprocket']);

        $widget->delete();

        $entry = AuditEntry::query()->where('event', 'audited_widget.deleted')->sole();

        $this->assertTrue($entry->subject->is($widget));
    }

    public function test_sensitive_attributes_show_as_changed_but_their_values_are_never_stored(): void
    {
        $widget = AuditedWidget::create([
            'name' => 'Sprocket',
            'pin' => 'hidden-value-1',
            'licence' => 'encrypted-value-1',
            'api_token' => 'named-value-1',
        ]);

        $widget->update([
            'pin' => 'hidden-value-2',
            'licence' => 'encrypted-value-2',
            'api_token' => 'named-value-2',
        ]);

        $stored = DB::table('activity_log')->pluck('attribute_changes')->implode(' ');

        foreach (['hidden-value', 'encrypted-value', 'named-value'] as $secret) {
            $this->assertStringNotContainsString($secret, $stored);
        }

        $changes = AuditEntry::query()->where('event', 'audited_widget.updated')->sole()->attribute_changes;

        foreach (['pin', 'licence', 'api_token'] as $attribute) {
            $this->assertSame(LogAuditEntry::REDACTED, $changes['attributes'][$attribute]);
            $this->assertSame(LogAuditEntry::REDACTED, $changes['old'][$attribute]);
        }
    }

    public function test_a_sensitive_attribute_keeps_null_so_set_and_cleared_stay_distinguishable(): void
    {
        $widget = AuditedWidget::create(['name' => 'Sprocket']);

        $widget->update(['pin' => 'hidden-value']);

        $changes = AuditEntry::query()->where('event', 'audited_widget.updated')->sole()->attribute_changes;

        $this->assertNull($changes['old']['pin']);
        $this->assertSame(LogAuditEntry::REDACTED, $changes['attributes']['pin']);
    }

    public function test_sensitive_names_inside_a_json_attribute_are_redacted(): void
    {
        AuditedWidget::create(['name' => 'Sprocket', 'settings' => ['smtp' => ['password' => 'nested-value']]]);

        $this->assertStringNotContainsString('nested-value', DB::table('activity_log')->value('attribute_changes'));
    }

    public function test_an_excepted_attribute_is_left_out_and_changing_only_it_logs_nothing(): void
    {
        $widget = AuditedWidget::create(['name' => 'Sprocket', 'views' => 1]);

        $this->assertArrayNotHasKey('views', AuditEntry::sole()->attribute_changes['attributes']);

        $widget->update(['views' => 2]);

        $this->assertSame(1, AuditEntry::count());
    }

    public function test_long_values_are_cut(): void
    {
        config(['audit.max_value_length' => 10]);

        AuditedWidget::create(['name' => 'Sprocket', 'body' => str_repeat('a', 50)]);

        $this->assertSame(str_repeat('a', 10).'...', AuditEntry::sole()->attribute_changes['attributes']['body']);
    }

    public function test_encrypted_and_hashed_casts_are_redacted_whichever_form_they_take(): void
    {
        $widget = AuditedWidget::create([
            'name' => 'Sprocket',
            'vault' => ['key' => 'vault-value'],
            'vault_object' => ['key' => 'vault-value'],
            'hashed_code' => 'plain-code',
        ]);

        $stored = DB::table('activity_log')->value('attribute_changes');

        $this->assertStringNotContainsString('vault-value', $stored);
        $this->assertStringNotContainsString($widget->getRawOriginal('hashed_code'), $stored);
    }

    public function test_an_attribute_made_visible_at_runtime_is_still_redacted(): void
    {
        $widget = AuditedWidget::create(['name' => 'Sprocket']);

        $widget->makeVisible('pin')->update(['pin' => 'hidden-value']);

        $this->assertStringNotContainsString('hidden-value', DB::table('activity_log')->pluck('attribute_changes')->implode(' '));
    }

    public function test_a_model_listing_visible_attributes_has_everything_else_redacted(): void
    {
        AuditedWidgetWithVisible::create(['name' => 'Sprocket', 'body' => 'not-listed-value']);

        $attributes = AuditEntry::sole()->attribute_changes['attributes'];

        $this->assertSame('Sprocket', $attributes['name']);
        $this->assertSame(LogAuditEntry::REDACTED, $attributes['body']);
    }

    public function test_a_sensitive_name_inside_a_collection_attribute_is_redacted(): void
    {
        AuditedWidget::create(['name' => 'Sprocket', 'options' => ['smtp' => ['password' => 'nested-value']]]);

        $this->assertStringNotContainsString('nested-value', DB::table('activity_log')->value('attribute_changes'));
    }

    public function test_a_subject_hidden_attribute_is_redacted_from_properties_too(): void
    {
        $widget = AuditedWidget::create(['name' => 'Sprocket']);

        Audit::record('widget.checked', $widget, ['pin' => 'hidden-value']);

        $this->assertStringNotContainsString('hidden-value', DB::table('activity_log')->pluck('properties')->implode(' '));
    }

    public function test_a_custom_description_on_a_model_event_is_kept(): void
    {
        $widget = AuditedWidget::create(['name' => 'Sprocket']);

        activity()->performedOn($widget)->event('updated')->log('Renamed by the importer');

        $entry = AuditEntry::query()->latest('id')->first();

        $this->assertSame('audited_widget.updated', $entry->event);
        $this->assertSame('Renamed by the importer', $entry->description);
    }

    public function test_an_entry_rolls_back_with_a_surrounding_transaction(): void
    {
        try {
            DB::transaction(function (): void {
                AuditedWidget::create(['name' => 'Sprocket']);

                throw new RuntimeException('The change failed.');
            });
        } catch (RuntimeException) {
            //
        }

        $this->assertSame(0, AuditEntry::count());
        $this->assertSame(0, AuditedWidget::count());
    }

    public function test_a_create_is_not_kept_if_its_entry_cannot_be_written(): void
    {
        $this->failAuditWrites(fn () => AuditedWidget::create(['name' => 'Sprocket']));

        $this->assertSame(0, AuditedWidget::count());
    }

    public function test_an_update_is_not_kept_if_its_entry_cannot_be_written(): void
    {
        $widget = AuditedWidget::create(['name' => 'Sprocket']);

        $this->failAuditWrites(fn () => $widget->update(['name' => 'Cog']));

        $this->assertSame('Sprocket', $widget->fresh()->name);
    }

    public function test_a_soft_delete_is_not_kept_if_its_entry_cannot_be_written(): void
    {
        $widget = AuditedWidget::create(['name' => 'Sprocket']);

        $this->failAuditWrites(fn () => $widget->delete());

        $this->assertFalse($widget->fresh()->trashed());
    }

    public function test_a_force_delete_is_not_kept_if_its_entry_cannot_be_written(): void
    {
        $widget = AuditedWidget::create(['name' => 'Sprocket']);

        $this->failAuditWrites(fn () => $widget->forceDelete());

        $this->assertNotNull(AuditedWidget::query()->find($widget->getKey()));
    }

    public function test_a_restore_is_not_kept_if_its_entry_cannot_be_written(): void
    {
        $widget = AuditedWidget::create(['name' => 'Sprocket']);
        $widget->delete();

        $this->failAuditWrites(fn () => $widget->restore());

        $this->assertTrue($widget->fresh()->trashed());
    }

    public function test_an_increment_is_not_kept_if_its_entry_cannot_be_written(): void
    {
        $widget = AuditedWidget::create(['name' => 'Sprocket', 'body' => 'Unchanged']);

        $this->failAuditWrites(fn () => $widget->increment('views', 1, ['body' => 'Changed']));

        $this->assertSame(0, (int) AuditedWidget::query()->value('views'));
    }

    public function test_every_restore_is_logged_even_after_one_made_with_a_plain_save(): void
    {
        $widget = AuditedWidget::create(['name' => 'Sprocket']);
        $widget->delete();
        $widget->deleted_at = null;
        $widget->save();

        $widget->delete();
        $widget->restore();

        $this->assertSame(2, AuditEntry::query()->where('event', 'audited_widget.restored')->count());
    }

    public function test_restoring_a_model_that_is_not_deleted_logs_nothing(): void
    {
        $widget = AuditedWidget::create(['name' => 'Sprocket']);

        $this->assertTrue($widget->restore());

        $this->assertSame(0, AuditEntry::query()->where('event', 'audited_widget.restored')->count());
    }

    public function test_a_restore_vetoed_while_saving_logs_nothing(): void
    {
        $widget = AuditedWidget::create(['name' => 'Sprocket']);
        $widget->delete();
        AuditedWidget::saving(fn (): bool => false);

        $this->assertFalse($widget->restore());

        $this->assertTrue(AuditedWidget::withTrashed()->sole()->trashed());
        $this->assertSame(0, AuditEntry::query()->where('event', 'audited_widget.restored')->count());
    }

    public function test_a_pivot_can_be_audited_and_deleted(): void
    {
        AuditedPivot::createTable();
        $pivot = (new AuditedPivot(['widget_id' => 1, 'tag_id' => 2]))->setPivotKeys('widget_id', 'tag_id');
        $pivot->save();

        $pivot->delete();

        $this->assertSame(0, AuditedPivot::query()->count());
        $this->assertSame(1, AuditEntry::query()->where('event', 'audited_pivot.deleted')->count());
    }

    public function test_a_subclass_can_override_save_and_delete_as_eloquent_declares_them(): void
    {
        $widget = AuditedWidgetWithOwnWrites::create(['name' => 'Sprocket']);

        $this->assertTrue($widget->delete());
        $this->assertSame(2, AuditEntry::count());
    }

    public function test_after_a_failed_restore_only_a_fresh_instance_restores_and_logs(): void
    {
        $widget = AuditedWidget::create(['name' => 'Sprocket']);
        $widget->delete();
        $this->failAuditWrites(fn () => $widget->restore());

        $widget->restore();

        $this->assertTrue(AuditedWidget::withTrashed()->sole()->trashed());
        $this->assertSame(0, AuditEntry::query()->where('event', 'audited_widget.restored')->count());

        AuditedWidget::withTrashed()->sole()->restore();

        $this->assertFalse(AuditedWidget::query()->sole()->trashed());
        $this->assertSame(1, AuditEntry::query()->where('event', 'audited_widget.restored')->count());
    }

    public function test_a_causer_whose_name_is_a_stringable_is_named(): void
    {
        $causer = AuditedWidget::create(['name' => 'Sprocket'])->mergeCasts(['name' => AsStringable::class]);

        Audit::record('widget.checked', by: $causer);

        $this->assertSame('Sprocket', AuditEntry::query()->where('event', 'widget.checked')->sole()->actor_name);
    }

    public function test_a_causer_whose_name_is_not_text_does_not_stop_the_entry(): void
    {
        $causer = AuditedWidget::create(['name' => '5'])->mergeCasts(['name' => 'integer']);

        Audit::record('widget.checked', by: $causer);

        $this->assertNull(AuditEntry::query()->where('event', 'widget.checked')->sole()->actor_name);
    }

    public function test_an_attribute_hidden_at_runtime_is_redacted(): void
    {
        $widget = AuditedWidget::create(['name' => 'Sprocket']);

        $widget->makeHidden('name')->update(['name' => 'runtime-hidden-value']);

        $this->assertStringNotContainsString('runtime-hidden-value', DB::table('activity_log')->pluck('attribute_changes')->implode(' '));
    }

    public function test_a_subject_hidden_attribute_is_redacted_from_top_level_changes(): void
    {
        $widget = AuditedWidget::create(['name' => 'Sprocket']);

        activity()->performedOn($widget)->withChanges(['pin' => 'hidden-value'])->log('Changed');

        $this->assertStringNotContainsString('hidden-value', DB::table('activity_log')->pluck('attribute_changes')->implode(' '));
    }

    public function test_a_subject_hidden_attribute_is_redacted_when_nested_in_properties_in_any_case(): void
    {
        $widget = AuditedWidget::create(['name' => 'Sprocket']);

        Audit::record('widget.checked', $widget, ['before' => ['pin' => 'hidden-value', 'LICENCE' => 'hidden-value', 'name' => 'Sprocket']]);

        $entry = AuditEntry::query()->where('event', 'widget.checked')->sole();

        $this->assertStringNotContainsString('hidden-value', DB::table('activity_log')->where('id', $entry->id)->value('properties'));
        $this->assertSame('Sprocket', $entry->getProperty('before.name'));
    }

    public function test_restoring_fires_the_restored_event_once(): void
    {
        $fired = 0;
        AuditedWidget::restored(function () use (&$fired): void {
            $fired++;
        });
        $widget = AuditedWidget::create(['name' => 'Sprocket']);
        $widget->delete();

        $this->assertTrue($widget->restore());

        $this->assertSame(1, $fired);
        $this->assertSame(1, AuditEntry::query()->where('event', 'audited_widget.restored')->count());
    }

    public function test_nothing_is_logged_inside_without_logging(): void
    {
        Audit::withoutLogging(fn () => AuditedWidget::create(['name' => 'Sprocket']));

        $this->assertSame(0, AuditEntry::count());

        AuditedWidget::create(['name' => 'Cog']);

        $this->assertSame(1, AuditEntry::count());
    }

    public function test_nothing_is_logged_when_audit_is_disabled(): void
    {
        config(['activitylog.enabled' => false]);
        $this->app->forgetInstance(ActivityLogStatus::class);
        $this->app->forgetScopedInstances();

        AuditedWidget::create(['name' => 'Sprocket']);
        Audit::record('invoice.sent');

        $this->assertSame(0, AuditEntry::count());
    }

    /** Runs a write with the audit log failing, and expects the failure to reach the caller. */
    private function failAuditWrites(Closure $write): void
    {
        LogActivityAction::beforeLogging(fn () => throw new RuntimeException('The audit log is down.'));

        try {
            $write();
            $this->fail('The write succeeded without its audit entry.');
        } catch (RuntimeException $exception) {
            $this->assertSame('The audit log is down.', $exception->getMessage());
        } finally {
            LogActivityAction::clearBeforeLoggingCallbacks();
        }
    }
}
