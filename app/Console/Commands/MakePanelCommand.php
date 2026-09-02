<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/** Scaffolds a new admin page panel (see docs/architecture.md). */
class MakePanelCommand extends Command
{
    protected $signature = 'bp:make:panel {name? : The class name, e.g. TeamMembershipsPanel}';

    protected $description = 'Create a new admin page panel (a Show card and/or an Edit form section).';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name') ?: text('Panel class name (e.g. TeamMembershipsPanel)', required: true));

        $kind = select(
            label: 'What kind of panel is this?',
            options: [
                'show' => 'Show panel (read-only card)',
                'form' => 'Form section (editable fields)',
                'both' => 'Both',
            ],
            default: 'show',
        );

        $region = $kind === 'form' ? 'Main' : Str::studly(select(
            label: 'Which region should it render in on Show pages?',
            options: ['main' => 'Main column', 'sidebar' => 'Sidebar'],
            default: 'sidebar',
        ));

        $path = app_path("Panels/Users/{$name}.php");

        if (File::exists($path)) {
            $this->components->error("{$name} already exists at {$path}.");

            return self::FAILURE;
        }

        $stub = match ($kind) {
            'show' => 'panel.show.stub',
            'form' => 'panel.form.stub',
            'both' => 'panel.show-form.stub',
        };

        $contents = str_replace(
            ['{{ class }}', '{{ region }}'],
            [$name, $region],
            File::get(base_path("stubs/{$stub}")),
        );

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);

        $this->components->info("Created App\\Panels\\Users\\{$name}.");
        $this->components->warn('Register it in App\Support\Panels\AdminPanels, or call PanelRegistry::for(...)->add(...) from a service provider if this is an add-on.');

        return self::SUCCESS;
    }
}
