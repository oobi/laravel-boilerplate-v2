<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Spatie\LaravelSettings\Settings;

/**
 * An add-on's settings class for the Settings screen tests: an encrypted value,
 * a secret that isn't encrypted (so only SecretInput keeps it off the page), and
 * one its section doesn't show. WidgetNoteSection shows the rest.
 */
class WidgetSettings extends Settings
{
    public string $greeting;

    public ?string $api_key;

    public ?string $webhook_secret;

    public string $internal_note;

    public int $daily_token_allowance;

    public static function group(): string
    {
        return 'widgets';
    }

    public static function encrypted(): array
    {
        return ['api_key'];
    }
}
