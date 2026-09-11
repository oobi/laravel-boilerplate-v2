<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The app's own additions to spatie's roles table, kept together so spatie's
 * published create_permission_tables migration stays pristine: a badge `color`,
 * and a `scope` (`system` by default — see App\Models\Role::SYSTEM_SCOPE;
 * add-ons such as the teams tier contribute their own scope values).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('permission.table_names')['roles'], function (Blueprint $table): void {
            $table->string('color')->nullable()->after('name');
            $table->string('scope')->default('system')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table(config('permission.table_names')['roles'], function (Blueprint $table): void {
            $table->dropColumn(['color', 'scope']);
        });
    }
};
