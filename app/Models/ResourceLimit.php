<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ResourceLimit extends Model
{
    protected $guarded = [];

    protected $casts = [
        'cpus' => 'float',
        'cpuset' => 'string',
        'cpu_shares' => 'integer',
        'mem_limit' => 'string',
        'memswap_limit' => 'string',
        'mem_swappiness' => 'integer',
        'mem_reservation' => 'string',
    ];

    /**
     * List of resource limit field names (matches docker-compose column names).
     */
    public const FIELDS = [
        'cpus',
        'cpuset',
        'cpu_shares',
        'mem_limit',
        'memswap_limit',
        'mem_swappiness',
        'mem_reservation',
    ];

    /**
     * Get the parent resource model (Application, StandalonePostgresql, etc.).
     */
    public function resource(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get all fields with default values (all null).
     */
    public static function getFieldsWithDefaults(): array
    {
        return array_fill_keys(self::FIELDS, null);
    }

    /**
     * Get legacy column default values (original database defaults).
     * Used when resetting legacy columns during migration.
     * Note: These use the old 'limits_*' column names for legacy tables.
     */
    public static function getLegacyDefaults(): array
    {
        return [
            'limits_cpus' => '0',
            'limits_cpuset' => null,
            'limits_cpu_shares' => 1024,
            'limits_memory' => '0',
            'limits_memory_swap' => '0',
            'limits_memory_swappiness' => 60,
            'limits_memory_reservation' => '0',
        ];
    }

    /**
     * Map new column names to legacy column names for migration purposes.
     */
    public static function getNewToLegacyMapping(): array
    {
        return [
            'cpus' => 'limits_cpus',
            'cpuset' => 'limits_cpuset',
            'cpu_shares' => 'limits_cpu_shares',
            'mem_limit' => 'limits_memory',
            'memswap_limit' => 'limits_memory_swap',
            'mem_swappiness' => 'limits_memory_swappiness',
            'mem_reservation' => 'limits_memory_reservation',
        ];
    }

    /**
     * Map legacy column names to new column names for migration purposes.
     */
    public static function getLegacyToNewMapping(): array
    {
        return array_flip(self::getNewToLegacyMapping());
    }

    /**
     * Check if any limit is set (non-null).
     */
    public function hasAnyLimitsSet(): bool
    {
        foreach (self::FIELDS as $key) {
            if ($this->{$key} !== null) {
                return true;
            }
        }

        return false;
    }
}
