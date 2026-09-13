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
            // Co-owner: shielded (only the primary owner or a system admin may change
            // their role, suspend or remove them) but granted no permissions by the flag —
            // those come from their team role. The primary owner stays `teams.user_id`.
            // See Team::isOwnedBy().
            $table->boolean('is_owner')->default(false);
            // Suspended by a team admin: membership and role kept, access to the team withheld
            // until reinstated (the team-level counterpart of a deactivated account).
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();

            // No `role` string column (the drift vector in the predecessor app): a
            // member's team roles are foreign keys to `roles` rows, in team_user_role.
            $table->unique(['team_id', 'user_id']);
        });

        // A member's team roles: the membership's own pivot to spatie `roles` rows
        // (scope `team`). Stock spatie handles roles and their permissions; only the
        // per-team ASSIGNMENT is the app's, so spatie's teams feature is never
        // enabled and system roles resolve exactly as spatie documents. Cascades
        // both ways: leaving the team or deleting the role drops the assignment.
        Schema::create('team_user_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_user_id')->constrained('team_user')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['team_user_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_user_role');
        Schema::dropIfExists('team_user');
    }
};
