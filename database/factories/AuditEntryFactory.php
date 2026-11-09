<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AuditEntry;
use App\Support\Audit\AuditActor;
use App\Support\Audit\AuditCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditEntry>
 */
class AuditEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'log_name' => AuditCategory::DATA->value,
            'event' => 'record.updated',
            'description' => 'Record updated',
            'actor_type' => AuditActor::SYSTEM,
        ];
    }
}
