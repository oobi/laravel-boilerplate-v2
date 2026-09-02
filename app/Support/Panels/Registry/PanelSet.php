<?php

declare(strict_types=1);

namespace App\Support\Panels\Registry;

/** The ordered list of panel classes registered for one page key — fetch-or-create via PanelRegistry::for(). */
final class PanelSet
{
    /** @var list<class-string> */
    public array $classes = [];

    public function __construct(public readonly string $key) {}

    public function add(string ...$classes): static
    {
        array_push($this->classes, ...$classes);

        return $this;
    }
}
