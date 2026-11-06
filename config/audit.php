<?php

/*
 * The boilerplate's audit settings, on top of config/activitylog.php.
 * See docs/boilerplate/audit-log.md.
 */
return [

    /*
     * Attribute and property names recorded as changed but never with their
     * value, with * as a wildcard. Case and separators are ignored, so *apikey*
     * matches apiKey, api_key and X-Api-Key. Added to each model's $hidden
     * attributes and encrypted or hashed casts, which are always redacted.
     */
    'redact' => [
        '*password*',
        '*passwd*',
        '*passcode*',
        '*passphrase*',
        '*pwd*',
        '*token*',
        '*secret*',
        '*credential*',
        '*recoverycode*',
        '*totp*',
        '*apikey*',
        '*accesskey*',
        '*privatekey*',
        '*encryptionkey*',
        '*signingkey*',
        '*bearer*',
        '*jwt*',
        '*sessionid*',
        '*authorization',
        '*authorizationheader*',
        '*cookie',
        '*cookies',
    ],

    /*
     * Longer string values are cut to this many characters, so a large text
     * column doesn't bloat every entry about its record.
     */
    'max_value_length' => 2000,

    /*
     * Scheduled tasks that finish successfully. Failures and skips are always
     * logged. Turn this off if a frequent task makes it noisy.
     */
    'log_schedule_success' => env('AUDIT_LOG_SCHEDULE_SUCCESS', true),
];
