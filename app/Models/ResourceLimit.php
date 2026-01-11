<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ResourceLimit extends Model
{
    protected $guarded = [];

    protected $casts = [
        'limits_cpus' => 'string',
        'limits_cpuset' => 'string',
        'limits_cpu_shares' => 'integer',
        'limits_memory' => 'string',
        'limits_memory_swap' => 'string',
        'limits_memory_swappiness' => 'integer',
        'limits_memory_reservation' => 'string',
    ];

    /**
     * Default values for resource limits.
     * Used for comparison when detecting legacy vs fresh resources.
     */
    public const DEFAULTS = [
        'limits_cpus' => '0',
        'limits_cpuset' => null,
        'limits_cpu_shares' => 1024,
        'limits_memory' => '0',
        'limits_memory_swap' => '0',
        'limits_memory_swappiness' => 60,
        'limits_memory_reservation' => '0',
    ];

    /**
     * Get the parent resource model (Application, StandalonePostgresql, etc.).
     */
    public function resource(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Check if all limits are at default values.
     */
    public function hasDefaultValues(): bool
    {
        foreach (self::DEFAULTS as $key => $default) {
            $currentValue = $this->{$key};

            // Handle null comparison
            if ($default === null) {
                if ($currentValue !== null && $currentValue !== '') {
                    return false;
                }

                continue;
            }

            // Compare as strings for consistency
            if ((string) $currentValue !== (string) $default) {
                return false;
            }
        }

        return true;
    }
}
