<?php

declare(strict_types=1);

namespace Concise\ThemeDemo\Support;

use Illuminate\Support\Str;

/**
 * Shared ~17 synthetic extra columns for the "wide" table demos (Filament
 * and daisyUI both use these) — combined with the 3 base columns (name/
 * status/joined) that's ~20 total, purely for scroll/responsive testing,
 * not real data.
 */
class WideDemoColumns
{
    private const OPTIONS = [
        'department' => ['Engineering', 'Sales', 'Support', 'Marketing', 'Finance'],
        'region' => ['APAC', 'EMEA', 'AMER'],
        'manager' => ['J. Rivera', 'A. Chen', 'K. Novak', 'S. Patel'],
        'city' => ['Sydney', 'Austin', 'Berlin', 'Toronto', 'Osaka'],
        'country' => ['Australia', 'USA', 'Germany', 'Canada', 'Japan'],
        'timezone' => ['UTC+10', 'UTC-6', 'UTC+1', 'UTC-5', 'UTC+9'],
        'currency' => ['AUD', 'USD', 'EUR', 'CAD', 'JPY'],
        'plan' => ['Starter', 'Pro', 'Enterprise'],
        'source' => ['Referral', 'Organic', 'Ad campaign', 'Partner'],
        'billing_cycle' => ['Monthly', 'Annual'],
        'account_type' => ['Individual', 'Team', 'Enterprise'],
        'industry' => ['Retail', 'Healthcare', 'Education', 'Technology'],
        'referral_code' => ['SPRING24', 'SUMMER24', 'WINTER24', 'AUTUMN24'],
        'support_tier' => ['Standard', 'Priority', 'White-glove'],
        'language' => ['English', 'German', 'Japanese', 'French'],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return [...array_keys(self::OPTIONS), 'seats', 'renewal_date'];
    }

    public static function label(string $key): string
    {
        return match ($key) {
            'seats' => 'Seats',
            'renewal_date' => 'Renewal',
            default => Str::headline($key),
        };
    }

    /** @return array<string, mixed> */
    public static function valuesFor(DemoRow $row): array
    {
        $values = [];

        foreach (self::OPTIONS as $key => $options) {
            $values[$key] = $options[$row->id % count($options)];
        }

        $values['seats'] = ($row->id * 3) % 50 + 1;
        $values['renewal_date'] = now()->addDays($row->id * 17)->format('Y-m-d');

        return $values;
    }
}
