<?php

namespace Tests\Unit\Support;

use App\Support\Panels\Concerns\HasPanelMetadata;
use App\Support\Panels\PanelRegion;
use App\Support\Panels\PanelRegistry;
use App\Support\Panels\ShowPanel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Tests\TestCase;

class PanelRegistryTest extends TestCase
{
    protected function tearDown(): void
    {
        PanelRegistry::flush();

        parent::tearDown();
    }

    public function test_it_resolves_and_sorts_panels_from_config(): void
    {
        config(['panels.test.show' => [SecondPanelStub::class, FirstPanelStub::class]]);

        $panels = PanelRegistry::showPanels('test.show', new PanelTestSubject);

        $this->assertSame(['first', 'second'], $panels->map->key()->all());
    }

    public function test_it_filters_out_non_visible_panels(): void
    {
        config(['panels.test.show' => [FirstPanelStub::class, HiddenPanelStub::class]]);

        $panels = PanelRegistry::showPanels('test.show', new PanelTestSubject);

        $this->assertSame(['first'], $panels->map->key()->all());
    }

    public function test_panels_report_their_region(): void
    {
        config(['panels.test.show' => [FirstPanelStub::class, SidebarPanelStub::class]]);

        $panels = PanelRegistry::showPanels('test.show', new PanelTestSubject);

        $this->assertSame(PanelRegion::Main, $panels->first(fn (ShowPanel $panel): bool => $panel->key() === 'first')->region());
        $this->assertSame(PanelRegion::Sidebar, $panels->first(fn (ShowPanel $panel): bool => $panel->key() === 'sidebar')->region());
    }

    public function test_extend_appends_a_panel_without_touching_config(): void
    {
        config(['panels.test.show' => [FirstPanelStub::class]]);

        PanelRegistry::extend('test.show', SecondPanelStub::class);

        $panels = PanelRegistry::showPanels('test.show', new PanelTestSubject);

        $this->assertSame(['first', 'second'], $panels->map->key()->all());
    }

    public function test_find_resolves_a_single_panel_by_key(): void
    {
        config(['panels.test.show' => [FirstPanelStub::class, SecondPanelStub::class]]);

        $panel = PanelRegistry::find('test.show', 'second', new PanelTestSubject);

        $this->assertInstanceOf(SecondPanelStub::class, $panel);
    }
}

/** Minimal concrete Model so panels have a $subject without touching a database. */
class PanelTestSubject extends Model {}

class FirstPanelStub implements ShowPanel
{
    use HasPanelMetadata;

    public function key(): string
    {
        return 'first';
    }

    public function render(Model $subject): Htmlable
    {
        return new HtmlString('first');
    }
}

class SecondPanelStub implements ShowPanel
{
    use HasPanelMetadata;

    public function key(): string
    {
        return 'second';
    }

    public function order(): int
    {
        return 10;
    }

    public function render(Model $subject): Htmlable
    {
        return new HtmlString('second');
    }
}

class HiddenPanelStub implements ShowPanel
{
    use HasPanelMetadata;

    public function key(): string
    {
        return 'hidden';
    }

    public function visible(Model $subject, ?Authenticatable $viewer): bool
    {
        return false;
    }

    public function render(Model $subject): Htmlable
    {
        return new HtmlString('hidden');
    }
}

class SidebarPanelStub implements ShowPanel
{
    use HasPanelMetadata;

    public function key(): string
    {
        return 'sidebar';
    }

    public function region(): PanelRegion
    {
        return PanelRegion::Sidebar;
    }

    public function render(Model $subject): Htmlable
    {
        return new HtmlString('sidebar');
    }
}
