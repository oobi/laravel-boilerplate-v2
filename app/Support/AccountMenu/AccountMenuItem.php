<?php

declare(strict_types=1);

namespace App\Support\AccountMenu;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A single link in the header account menu (the avatar dropdown). Contributed
 * by an add-on through {@see AccountMenuRegistry} — core never hardcodes these,
 * the same way sidebar nav goes through NavRegistry. Label and URL may be
 * closures so a relabelled or per-request value is resolved at render, not
 * frozen at boot.
 */
final class AccountMenuItem
{
    protected Closure|string $label = '';

    protected Closure|string $url = '#';

    protected ?string $icon = null;

    protected int $order = 0;

    /** @var (Closure(?Authenticatable): bool)|null */
    protected ?Closure $visible = null;

    public function __construct(public readonly string $name) {}

    public function label(Closure|string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function url(Closure|string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function icon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function order(int $order): static
    {
        $this->order = $order;

        return $this;
    }

    /** @param  Closure(?Authenticatable): bool  $callback */
    public function visibleWhen(Closure $callback): static
    {
        $this->visible = $callback;

        return $this;
    }

    public function getLabel(): string
    {
        return (string) value($this->label);
    }

    public function getUrl(): string
    {
        return (string) value($this->url);
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    public function isVisible(?Authenticatable $viewer): bool
    {
        return $this->visible === null || ($this->visible)($viewer);
    }
}
