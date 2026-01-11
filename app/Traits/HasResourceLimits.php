<?php

namespace App\Traits;

use App\Models\ResourceLimit;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasResourceLimits
{
    /**
     * Get the resource limits relationship
     */
    public function resourceLimits(): MorphOne
    {
        return $this->morphOne(ResourceLimit::class, 'resource');
    }

    /**
     * Determine the source of resource limits for this resource.
     *
     * @return string 'new' | 'legacy' | 'fresh'
     */
    public function getResourceLimitsSource(): string
    {
        // Check if new pattern is in use (record exists in resource_limits table)
        if ($this->usesNewResourceLimits()) {
            return 'new';
        }

        // Check if legacy pattern has non-default values
        if ($this->hasLegacyResourceLimits()) {
            return 'legacy';
        }

        // No limits configured - fresh resource
        return 'fresh';
    }

    /**
     * Check if the resource uses the new resource_limits table.
     */
    public function usesNewResourceLimits(): bool
    {
        return $this->resourceLimits()->exists();
    }

    /**
     * Check if the resource has legacy direct columns with non-default values.
     * Only applicable to models that have direct limits_* columns.
     */
    public function hasLegacyResourceLimits(): bool
    {
        // Check if this model has the legacy columns
        if (!$this->hasLegacyResourceLimitColumns()) {
            return false;
        }

        // Check each column against defaults
        foreach (ResourceLimit::DEFAULTS as $column => $default) {
            $currentValue = $this->{$column} ?? null;

            // Handle null comparison
            if ($default === null) {
                if ($currentValue !== null && $currentValue !== '') {
                    return true;
                }

                continue;
            }

            // Compare as strings for consistency (database may return different types)
            if ((string) $currentValue !== (string) $default) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if this model has the legacy direct limit columns in its table.
     */
    public function hasLegacyResourceLimitColumns(): bool
    {
        // Check if the model's table has the limits_cpus column as a proxy
        return $this->getConnection()
            ->getSchemaBuilder()
            ->hasColumn($this->getTable(), 'limits_cpus');
    }

    /**
     * Get the effective resource limits, regardless of storage pattern.
     * Returns an array with all limit values.
     */
    public function getEffectiveResourceLimits(): array
    {
        $source = $this->getResourceLimitsSource();

        if ($source === 'new') {
            $limits = $this->resourceLimits;

            return [
                'limits_cpus' => $limits->limits_cpus,
                'limits_cpuset' => $limits->limits_cpuset,
                'limits_cpu_shares' => $limits->limits_cpu_shares,
                'limits_memory' => $limits->limits_memory,
                'limits_memory_swap' => $limits->limits_memory_swap,
                'limits_memory_swappiness' => $limits->limits_memory_swappiness,
                'limits_memory_reservation' => $limits->limits_memory_reservation,
            ];
        }

        if ($source === 'legacy') {
            return [
                'limits_cpus' => $this->limits_cpus,
                'limits_cpuset' => $this->limits_cpuset,
                'limits_cpu_shares' => $this->limits_cpu_shares,
                'limits_memory' => $this->limits_memory,
                'limits_memory_swap' => $this->limits_memory_swap,
                'limits_memory_swappiness' => $this->limits_memory_swappiness,
                'limits_memory_reservation' => $this->limits_memory_reservation,
            ];
        }

        // Fresh - return defaults
        return ResourceLimit::DEFAULTS;
    }

    /**
     * Migrate legacy resource limits to the new table structure.
     * Creates a new record in resource_limits and resets legacy columns to defaults.
     */
    public function migrateResourceLimitsToNewStructure(): bool
    {
        if (!$this->hasLegacyResourceLimits()) {
            return false;
        }

        if ($this->usesNewResourceLimits()) {
            return false;
        }

        return \DB::transaction(function () {
            // Create new record with current legacy values
            $this->resourceLimits()->create([
                'limits_cpus' => $this->limits_cpus,
                'limits_cpuset' => $this->limits_cpuset,
                'limits_cpu_shares' => $this->limits_cpu_shares,
                'limits_memory' => $this->limits_memory,
                'limits_memory_swap' => $this->limits_memory_swap,
                'limits_memory_swappiness' => $this->limits_memory_swappiness,
                'limits_memory_reservation' => $this->limits_memory_reservation,
            ]);

            // Reset legacy columns to defaults
            $this->update([
                'limits_cpus' => ResourceLimit::DEFAULTS['limits_cpus'],
                'limits_cpuset' => ResourceLimit::DEFAULTS['limits_cpuset'],
                'limits_cpu_shares' => ResourceLimit::DEFAULTS['limits_cpu_shares'],
                'limits_memory' => ResourceLimit::DEFAULTS['limits_memory'],
                'limits_memory_swap' => ResourceLimit::DEFAULTS['limits_memory_swap'],
                'limits_memory_swappiness' => ResourceLimit::DEFAULTS['limits_memory_swappiness'],
                'limits_memory_reservation' => ResourceLimit::DEFAULTS['limits_memory_reservation'],
            ]);

            return true;
        });
    }

    /**
     * Save resource limits using the appropriate pattern.
     * New/fresh resources use the new table, legacy resources continue using direct columns.
     */
    public function saveResourceLimits(array $limits): void
    {
        $source = $this->getResourceLimitsSource();

        if ($source === 'new') {
            // Update existing record in resource_limits table
            $this->resourceLimits->update($limits);
        } elseif ($source === 'legacy') {
            // Update direct columns (legacy pattern)
            $this->update($limits);
        } else {
            // Fresh resource - create new record in resource_limits table
            $this->resourceLimits()->create($limits);
        }
    }
}
