<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;
use Illuminate\Database\Eloquent\Casts\AsEncryptedCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A project's own model with nothing but `use Auditable;` and the usual sensitive attributes. */
class AuditedWidget extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'audited_widgets';

    protected $guarded = [];

    protected $hidden = ['pin'];

    /** @var list<string> */
    protected array $auditExcept = ['views'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'licence' => 'encrypted',
            'settings' => 'array',
            'vault' => AsEncryptedCollection::class,
            'vault_object' => AsEncryptedArrayObject::class,
            'hashed_code' => 'hashed',
            'options' => AsCollection::class,
        ];
    }

    /** Temporary, because creating a real table commits the test's transaction on MySQL. */
    public static function createTable(): void
    {
        Schema::create('audited_widgets', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->string('name');
            $table->text('body')->nullable();
            $table->string('pin')->nullable();
            $table->text('licence')->nullable();
            $table->string('api_token')->nullable();
            $table->text('settings')->nullable();
            $table->text('vault')->nullable();
            $table->text('vault_object')->nullable();
            $table->string('hashed_code')->nullable();
            $table->text('options')->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
