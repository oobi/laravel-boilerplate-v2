<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A pivot with no id of its own, whose delete() returns an int. */
class AuditedPivot extends Pivot
{
    use Auditable;

    protected $table = 'audited_pivots';

    protected $guarded = [];

    public $timestamps = false;

    /** Temporary, because creating a real table commits the test's transaction on MySQL. */
    public static function createTable(): void
    {
        Schema::create('audited_pivots', function (Blueprint $table): void {
            $table->temporary();
            $table->unsignedBigInteger('widget_id');
            $table->unsignedBigInteger('tag_id');
        });
    }
}
