<?php

declare(strict_types=1);

namespace Tests\Fixtures;

/** A model that hides by listing what's visible, instead of what's hidden. */
class AuditedWidgetWithVisible extends AuditedWidget
{
    protected $visible = ['name'];
}
