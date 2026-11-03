<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Tables\Filters\TrashedFilter;
use Tests\TestCase;

/** The global trashed-filter default in AppServiceProvider names the trash view the way the toolbar's toggle does. */
class FilamentTrashedFilterDefaultsTest extends TestCase
{
    public function test_the_trash_view_is_called_trashed(): void
    {
        $this->assertSame(__('admin.records_trashed'), TrashedFilter::make()->getFalseLabel());
    }
}
