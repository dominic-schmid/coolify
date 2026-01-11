<?php

namespace App\Traits;

use App\Models\ResourceLimit;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasResourceLimits
{
    /**
     * Save resource limits from legacy field names (limits_*) directly to new structure.
     * Use this when creating new resources via API with legacy field names.
     *
     * @param array $legacyLimits Array with legacy field names (limits_*)
     * @return void
     */
    public function saveLegacyLimitsToNewStructure(array $legacyLimits): void
    {
        if (empty($legacyLimits)) {
            return;
        }

        // Map legacy field names to new field names
        $legacyToNew = ResourceLimit::getLegacyToNewMapping();
        $newLimits = [];
        $legacyDefaults = ResourceLimit::getLegacyDefaults();

        foreach ($legacyLimits as $legacyKey => $value) {
            if (!isset($legacyToNew[$legacyKey])) {
                continue; // Skip unknown fields
            }

            $newKey = $legacyToNew[$legacyKey];
            $defaultValue = $legacyDefaults[$legacyKey] ?? null;

            // Normalize memory values: "0m" is equivalent to "0" (legacy default)
            if (in_array($newKey, ['mem_limit', 'memswap_limit', 'mem_reservation'])) {
                $value = $this->normalizeMemoryValueForStorage($value);
            }

            // Convert legacy defaults to null in new structure
            if ($this->isLegacyDefaultValue($value, $defaultValue)) {
                $newLimits[$newKey] = null;
            } else {
                // Convert cpus from string to float if needed
                if ($newKey === 'cpus' && $value !== null) {
                    $newLimits[$newKey] = (float) $value;
                } else {
                    $newLimits[$newKey] = $value;
                }
            }
        }

        // Save to new structure
        if (!empty($newLimits)) {
            $this->saveResourceLimits($newLimits);
        }
    }

    /**
     * Normalize memory value for storage.
     * Converts "0" with any suffix (0m, 0M, 0mb, 0MB, 0g, 0G, etc.) back to "0" for legacy compatibility.
     */
    private function normalizeMemoryValueForStorage($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Convert "0" with any suffix back to "0" for legacy compatibility
        if (preg_match('/^0[a-zA-Z]+$/i', (string) $value)) {
            return '0';
        }

        return $value;
    }

    /**
     * Check if a legacy column value matches its legacy default value.
     */
    private function isLegacyDefaultValue($currentValue, $defaultValue): bool
    {
        // Both null = match
        if ($currentValue === null && $defaultValue === null) {
            return true;
        }

        // One is null, other isn't = no match
        if ($currentValue === null || $defaultValue === null) {
            return false;
        }

        // Cast both to strings and compare (handles int/string mismatches)
        return trim((string) $currentValue) === trim((string) $defaultValue);
    }

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
     * Only returns true if legacy columns have values that differ from their defaults.
     */
    public function hasLegacyResourceLimits(): bool
    {
        // Check if this model has the legacy columns
        if (!$this->hasLegacyResourceLimitColumns()) {
            return false;
        }

        // Check if any legacy column has a non-default value
        // Legacy columns use 'limits_*' prefix
        $legacyDefaults = ResourceLimit::getLegacyDefaults();
        $legacyToNew = ResourceLimit::getLegacyToNewMapping();

        foreach ($legacyToNew as $legacyKey => $newKey) {
            $currentValue = $this->{$legacyKey};
            $defaultValue = $legacyDefaults[$legacyKey] ?? null;

            // If value is null and default is null, continue (both are default)
            if ($currentValue === null && $defaultValue === null) {
                continue;
            }

            // If one is null and other isn't, it's non-default
            if ($currentValue === null || $defaultValue === null) {
                return true;
            }

            // Compare as strings for consistency (handles int/string mismatches)
            if (trim((string) $currentValue) !== trim((string) $defaultValue)) {
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
        // (legacy tables use 'limits_*' prefix, new table uses docker-compose names)
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
            $result = [];
            foreach (ResourceLimit::FIELDS as $key) {
                $result[$key] = $limits->{$key};
            }

            return $result;
        }

        if ($source === 'legacy') {
            // Legacy columns use 'limits_*' prefix, map to new names
            $legacyToNew = ResourceLimit::getLegacyToNewMapping();
            $result = [];
            foreach ($legacyToNew as $legacyKey => $newKey) {
                $value = $this->{$legacyKey};
                // Convert cpus from legacy string to float
                if ($newKey === 'cpus' && $value !== null) {
                    $value = (float) $value;
                }
                $result[$newKey] = $value;
            }

            return $result;
        }

        // Fresh - all null
        return ResourceLimit::getFieldsWithDefaults();
    }

    /**
     * Migrate legacy resource limits to the new table structure.
     * Creates a new record in resource_limits and resets legacy columns.
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
            // Map legacy values to new structure, converting defaults to null
            $legacyDefaults = ResourceLimit::getLegacyDefaults();
            $legacyToNew = ResourceLimit::getLegacyToNewMapping();
            $newValues = [];

            foreach ($legacyToNew as $legacyKey => $newKey) {
                $currentValue = $this->{$legacyKey};
                $defaultValue = $legacyDefaults[$legacyKey] ?? null;

                // Normalize memory values: "0m" is equivalent to "0" (legacy default)
                if (in_array($newKey, ['mem_limit', 'memswap_limit', 'mem_reservation'])) {
                    $currentValue = $this->normalizeMemoryValueForStorage($currentValue);
                }

                // If value matches legacy default, convert to null
                if ($this->isLegacyDefaultValue($currentValue, $defaultValue)) {
                    $newValues[$newKey] = null;
                } else {
                    // Convert cpus from string to float for new structure
                    if ($newKey === 'cpus' && $currentValue !== null) {
                        $newValues[$newKey] = (float) $currentValue;
                    } else {
                        $newValues[$newKey] = $currentValue;
                    }
                }
            }

            // Always create record during migration
            $this->resourceLimits()->create($newValues);

            // Reset legacy columns to their original database defaults
            // Skip the interceptor to avoid overwriting the newly created record
            $legacyDefaults = ResourceLimit::getLegacyDefaults();
            $columnsToReset = [];
            $schema = $this->getConnection()->getSchemaBuilder();

            foreach ($legacyDefaults as $key => $value) {
                if ($schema->hasColumn($this->getTable(), $key)) {
                    $columnsToReset[$key] = $value;
                }
            }

            if (!empty($columnsToReset)) {
                $this->update($columnsToReset);
            }

            return true;
        });
    }


    /**
     * Get resource limits formatted for docker-compose.
     * Returns an array with docker-compose keys (cpus, mem_limit, etc.)
     * Handles both new and legacy storage patterns.
     */
    public function getDockerComposeLimits(): array
    {
        $limits = $this->getEffectiveResourceLimits();
        // Filter out null values to keep docker-compose clean
        return array_filter($limits, fn($value) => $value !== null);
    }

    /**
     * Save resource limits using the appropriate pattern.
     * Always saves the limits (even if all null) - never deletes records.
     */
    public function saveResourceLimits(array $limits): void
    {
        $source = $this->getResourceLimitsSource();

        if ($source === 'new') {
            // Update existing record in resource_limits table (uses new column names)
            $this->resourceLimits->update($limits);
        } elseif ($source === 'legacy') {
            // Update direct columns (legacy pattern - map new names to legacy names)
            $newToLegacy = ResourceLimit::getNewToLegacyMapping();
            $legacyLimits = [];
            foreach ($limits as $newKey => $value) {
                if (isset($newToLegacy[$newKey])) {
                    // Normalize memory values: convert "0m" to "0" for legacy compatibility
                    if (in_array($newKey, ['mem_limit', 'memswap_limit', 'mem_reservation'])) {
                        $value = $this->normalizeMemoryValueForStorage($value);
                    }
                    // Convert cpus from float to string for legacy columns
                    if ($newKey === 'cpus' && $value !== null) {
                        $value = (string) $value;
                    }
                    $legacyLimits[$newToLegacy[$newKey]] = $value;
                }
            }
            $this->update($legacyLimits);
        } else {
            // Fresh resource - only create record if at least one limit is set
            // (Don't create empty records for fresh resources)
            $hasAnyLimit = false;
            foreach ($limits as $value) {
                if ($value !== null) {
                    $hasAnyLimit = true;
                    break;
                }
            }

            if ($hasAnyLimit) {
                $this->resourceLimits()->create($limits);
            }
        }
    }
}
