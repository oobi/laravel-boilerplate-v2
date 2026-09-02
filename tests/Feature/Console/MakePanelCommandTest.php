<?php

namespace Tests\Feature\Console;

use App\Panels\Users\TestDemoFormSection;
use App\Panels\Users\TestDemoShowPanel;
use App\Support\Panels\Contracts\FormSection;
use App\Support\Panels\Contracts\ShowPanel;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MakePanelCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        File::delete(app_path('Panels/Users/TestDemoShowPanel.php'));
        File::delete(app_path('Panels/Users/TestDemoFormSection.php'));

        parent::tearDown();
    }

    public function test_it_generates_a_show_panel(): void
    {
        $this->artisan('bp:make:panel', ['name' => 'TestDemoShowPanel'])
            ->expectsQuestion('What kind of panel is this?', 'show')
            ->expectsQuestion('Which region should it render in on Show pages?', 'sidebar')
            ->assertSuccessful();

        $path = app_path('Panels/Users/TestDemoShowPanel.php');
        $this->assertFileExists($path);

        require $path;

        $this->assertContains(ShowPanel::class, class_implements(TestDemoShowPanel::class));
    }

    public function test_it_generates_a_form_section(): void
    {
        $this->artisan('bp:make:panel', ['name' => 'TestDemoFormSection'])
            ->expectsQuestion('What kind of panel is this?', 'form')
            ->assertSuccessful();

        $path = app_path('Panels/Users/TestDemoFormSection.php');
        $this->assertFileExists($path);

        require $path;

        $this->assertContains(FormSection::class, class_implements(TestDemoFormSection::class));
    }
}
