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
     * @deprecated Legacy API parameter names (limits_*) will be removed in a future version.
     *             Use new docker-compose field names (cpus, mem_limit, etc.) instead.
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
     *
     * @deprecated Only used for legacy migration and API parameter conversion.
     *             Will be removed when legacy support is removed.
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
     *
     * @deprecated Only used for legacy migration and API parameter conversion.
     *             Will be removed when legacy support is removed.
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
     * Respects the current storage state to determine where limits are stored.
     *
     * @return string 'new' | 'legacy' | 'fresh'
     *   - 'new': Limits stored in resource_limits table (editable)
     *   - 'legacy': Limits stored in legacy direct columns (read-only, must migrate)
     *   - 'fresh': No limits configured yet
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
     *
     * @deprecated Legacy detection will be removed in a future version.
     *             All resources should be migrated to the new structure.
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
     *
     * @deprecated Legacy column detection will be removed in a future version.
     *             All resources should be migrated to the new structure.
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
     * Returns an array with all limit values using new docker-compose column names.
     *
     * This method respects the current storage state:
     * - 'new': Reads from resource_limits table
     * - 'legacy': Reads from legacy direct columns (read-only, must migrate to edit)
     * - 'fresh': Returns all null (no limits configured)
     *
     * @deprecated Legacy read support will be removed in a future version.
     *             All resources should be migrated to the new structure.
     */
    public function getEffectiveResourceLimits(): array
    {
        $source = $this->getResourceLimitsSource();

        if ($source === 'new') {
            // Read from new resource_limits table
            $limits = $this->resourceLimits;
            $result = [];
            foreach (ResourceLimit::FIELDS as $key) {
                $result[$key] = $limits->{$key};
            }

            return $result;
        }

        if ($source === 'legacy') {
            // Read from legacy columns (read-only, must migrate to edit)
            // TODO: Remove legacy read support in a future version
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

        // Fresh - no limits configured, return all null
        return ResourceLimit::getFieldsWithDefaults();
    }

    /**
     * Migrate legacy resource limits to the new table structure.
     * Creates a new record in resource_limits and resets legacy columns.
     *
     * @deprecated This method is only needed for legacy resources.
     *             Once all resources are migrated, this method will be removed.
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
            // NOTE: This is the ONLY place in the codebase that writes to legacy columns.
            // All other write paths use the new structure or throw an error.
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
     * Check if any resources of this type still use legacy storage.
     * Static helper method for checking migration status.
     *
     * @deprecated Legacy detection will be removed in a future version.
     *             All resources should be migrated to the new structure.
     *
     * @param string $modelClass The model class to check
     * @return bool True if any resources use legacy storage
     */
    public static function hasAnyLegacyResources(string $modelClass): bool
    {
        if (!method_exists($modelClass, 'hasLegacyResourceLimits')) {
            return false;
        }

        return $modelClass::query()
            ->get()
            ->contains(fn ($resource) => $resource->hasLegacyResourceLimits());
    }

    /**
     * Count all legacy resources across all model types.
     * Returns an array with model class basenames as keys and counts as values.
     * Only includes models that have at least one legacy resource.
     *
     * @return array<string, int> Array of model basename => count pairs
     */
    public static function countAllLegacyResources(): array
    {
        $models = [
            \App\Models\Application::class,
            \App\Models\Service::class,
            \App\Models\ServiceApplication::class,
            \App\Models\ServiceDatabase::class,
            \App\Models\StandalonePostgresql::class,
            \App\Models\StandaloneRedis::class,
            \App\Models\StandaloneMysql::class,
            \App\Models\StandaloneMariadb::class,
            \App\Models\StandaloneMongodb::class,
            \App\Models\StandaloneClickhouse::class,
            \App\Models\StandaloneKeydb::class,
            \App\Models\StandaloneDragonfly::class,
        ];

        $counts = [];
        foreach ($models as $model) {
            $count = $model::query()
                ->get()
                ->filter(fn ($resource) => $resource->hasLegacyResourceLimits())
                ->count();

            if ($count > 0) {
                $counts[class_basename($model)] = $count;
            }
        }

        return $counts;
    }

    /**
     * Get resource limits formatted for docker-compose.
     * Returns an array with docker-compose keys (cpus, mem_limit, etc.)
     *
     * This method respects the current storage state by using getEffectiveResourceLimits(),
     * which handles both new and legacy storage patterns (legacy is read-only).
     *
     * @return array Filtered array with only non-null values for docker-compose
     */
    public function getDockerComposeLimits(): array
    {
        $limits = $this->getEffectiveResourceLimits();
        // Filter out null values to keep docker-compose clean
        return array_filter($limits, fn($value) => $value !== null);
    }

    /**
     * Static helper to get docker-compose limits for any resource.
     * Handles both resources with HasResourceLimits trait and legacy resources.
     *
     * @param object $resource The resource (Application, StandaloneDatabase, etc.)
     * @return array Docker-compose limits array (cpus, mem_limit, etc.) with null values filtered out
     */
    public static function getDockerComposeLimitsForResource(object $resource): array
    {
        // Try new structure first (resources with HasResourceLimits trait)
        if (method_exists($resource, 'getDockerComposeLimits')) {
            return $resource->getDockerComposeLimits();
        }

        // Fallback for resources without trait (legacy direct access - read-only)
        // TODO: Remove legacy read support in a future version
        // @deprecated Legacy column access will be removed. All resources should use HasResourceLimits trait.
        $limits = [];

        if (!is_null($resource->limits_memory ?? null)) {
            $limits['mem_limit'] = $resource->limits_memory;
        }
        if (!is_null($resource->limits_memory_swap ?? null)) {
            $limits['memswap_limit'] = $resource->limits_memory_swap;
        }
        if (!is_null($resource->limits_memory_swappiness ?? null)) {
            $limits['mem_swappiness'] = $resource->limits_memory_swappiness;
        }
        if (!is_null($resource->limits_memory_reservation ?? null)) {
            $limits['mem_reservation'] = $resource->limits_memory_reservation;
        }
        if (!is_null($resource->limits_cpus ?? null)) {
            $limits['cpus'] = (float) $resource->limits_cpus;
        }
        if (!is_null($resource->limits_cpu_shares ?? null)) {
            $limits['cpu_shares'] = $resource->limits_cpu_shares;
        }
        // Use filled() for cpuset to match StartPostgresql behavior (excludes empty strings)
        if (filled($resource->limits_cpuset ?? null)) {
            $limits['cpuset'] = $resource->limits_cpuset;
        }

        // Filter out null values to keep docker-compose clean
        return array_filter($limits, fn($value) => $value !== null);
    }

    /**
     * Save resource limits using the new structure.
     *
     * Write path is dead simple:
     * - New structure: update existing record
     * - Legacy structure: throw error (read-only, must migrate first)
     * - Fresh resource: create new record if any limit is set
     *
     * NOTE: This is the ONLY write path for resource limits. All other code paths
     * must use this method. Legacy columns cannot be written to directly.
     *
     * @param array $limits Array with new docker-compose column names (cpus, mem_limit, etc.)
     * @throws \RuntimeException If resource uses legacy storage (must migrate first)
     */
    public function saveResourceLimits(array $limits): void
    {
        $source = $this->getResourceLimitsSource();

        if ($source === 'new') {
            // Update existing record in resource_limits table
            $this->resourceLimits->update($limits);
        } elseif ($source === 'legacy') {
            // Legacy resources are read-only - must migrate first
            throw new \RuntimeException(
                'Cannot update resource limits: legacy storage is read-only. Please migrate to new structure first.'
            );
        } else {
            // Fresh resource - create new record if any limit is set
            $hasAnyLimit = collect($limits)->contains(fn($v) => $v !== null);
            if ($hasAnyLimit) {
                $this->resourceLimits()->create($limits);
            }
        }
    }
}
