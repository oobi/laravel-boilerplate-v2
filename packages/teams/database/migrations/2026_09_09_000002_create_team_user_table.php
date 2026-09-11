<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Co-owner (Slack model): shares the primary owner's permission bypass;
            // the primary owner stays `teams.user_id`. See Team::isOwnedBy().
            $table->boolean('is_owner')->default(false);
            // Suspended by a team admin: membership and role kept, access to the team withheld
            // until reinstated (the team-level counterpart of a deactivated account).
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();

            // No `role` column: per-team roles live in spatie/laravel-permission
            // scoped by team_id (see ~dev/TEAMS_TIER_SCOPE.md §5).
            $table->unique(['team_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_user');
    }
};
