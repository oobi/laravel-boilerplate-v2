<?php

use App\Models\AuditEntry;
use App\Support\Audit\AuditCategory;
use App\Support\Audit\LogAuditEntry;
use Spatie\Activitylog\Actions\CleanActivityLogAction;

/*
 * spatie/laravel-activitylog's own settings. The boilerplate's additions are in
 * config/audit.php; see docs/boilerplate/audit-log.md.
 */
return [

    /*
     * AUDIT_ENABLED=false writes nothing and removes the audit screens.
     */
    'enabled' => env('AUDIT_ENABLED', true),

    /*
     * Entries older than this are removed by the daily clean. Read by the
     * schedule, where 0 means keep them forever.
     */
    'clean_after_days' => (int) env('AUDIT_RETENTION_DAYS', 365),

    /*
     * The category of an entry that doesn't name one.
     */
    'default_log_name' => AuditCategory::DATA->value,

    /*
     * Null uses the current Laravel auth guard.
     */
    'default_auth_driver' => null,

    /*
     * Keep soft deleted subjects reachable: a deleted record is what an entry
     * about its deletion is about.
     */
    'include_soft_deleted_subjects' => true,

    'activity_model' => AuditEntry::class,

    /*
     * Never worth an entry on their own, and noise in every change. Redaction
     * of sensitive attributes is separate: see config/audit.php.
     */
    'default_except_attributes' => ['created_at', 'updated_at'],

    /*
     * Off: an entry must commit or roll back with the change it describes.
     */
    'buffer' => [
        'enabled' => false,
    ],

    'actions' => [
        'log_activity' => LogAuditEntry::class,
        'clean_log' => CleanActivityLogAction::class,
    ],
];
