<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * spatie/laravel-activitylog's table plus who and where. The actor's and
 * impersonator's names are snapshots, so an entry stays readable after the user
 * is permanently deleted. Filtered columns are real columns and indexed, never
 * JSON paths, so the audit screen behaves the same on every database. The one
 * exception is search reading `properties->identifier` (the address typed into a
 * failed login): it is stored lowercased and searched with a lowercased term,
 * since MySQL compares JSON text case-sensitively.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table): void {
            $table->id();
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->nullableMorphs('subject');
            $table->string('event')->nullable()->index();
            $table->nullableMorphs('causer');
            $table->string('actor_type')->index();
            $table->string('actor_name')->nullable();
            $table->foreignId('impersonator_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->string('impersonator_name')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            // Links one browser session's entries (App\Support\Audit\SessionTrail); never the session ID.
            $table->ulid('session_trail')->nullable();
            $table->json('attribute_changes')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();

            // The screen and the export read newest first by when, then id.
            $table->index(['created_at', 'id']);
            // One session, in the order it happened.
            $table->index(['session_trail', 'created_at', 'id']);
            $table->index(['log_name', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
