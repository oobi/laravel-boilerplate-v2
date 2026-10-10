<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Theme\DaisyColor;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Tests\TestCase;

/** The global restore default in AppServiceProvider colours every restore as success, add-ons included. */
class FilamentRestoreDefaultsTest extends TestCase
{
    public function test_restore_actions_default_to_success(): void
    {
        $this->assertSame(DaisyColor::SUCCESS->toFilamentColor(), RestoreAction::make()->getColor());
    }

    public function test_restore_bulk_actions_default_to_success(): void
    {
        $this->assertSame(DaisyColor::SUCCESS->toFilamentColor(), RestoreBulkAction::make()->getColor());
    }
}
