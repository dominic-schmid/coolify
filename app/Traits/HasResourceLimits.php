<?php

namespace App\Traits;

use App\Models\ResourceLimit;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasResourceLimits
{
    /**
     * Temporarily store resource limits extracted from old fields during creation.
     *
     * @var array|null
     */
    public $pendingResourceLimits = null;

    /**
     * Boot the trait and register event listeners.
     */
    protected static function bootHasResourceLimits()
    {
        // Intercept old limit fields before saving
        static::saving(function ($model) {
            // Only intercept for new resources (not updating legacy ones)
            if ($model->exists) {
                // Existing resource - check if it's legacy
                $source = $model->getResourceLimitsSource();
                if ($source === 'legacy') {
                    // Don't intercept - let legacy resources continue using old columns
                    return;
                }
            }

            // Extract old limit fields from attributes
            $limitKeys = array_keys(ResourceLimit::DEFAULTS);
            $pendingLimits = [];

            foreach ($limitKeys as $key) {
                if (isset($model->attributes[$key])) {
                    $pendingLimits[$key] = $model->attributes[$key];
                    // Remove from attributes so it doesn't get saved to legacy column
                    unset($model->attributes[$key]);
                }
            }

            // Store for saving after model is persisted
            if (!empty($pendingLimits)) {
                $model->pendingResourceLimits = $pendingLimits;
            }
        });

        // Save intercepted limits to new structure after model is saved
        static::saved(function ($model) {
            if (isset($model->pendingResourceLimits) && !empty($model->pendingResourceLimits)) {
                // Save if resource is fresh (newly created) or already using new structure
                // Don't save for legacy resources (they weren't intercepted anyway)
                $source = $model->getResourceLimitsSource();
                if ($source === 'fresh' || $source === 'new') {
                    $model->saveResourceLimits($model->pendingResourceLimits);
                }
                // Clear the pending limits
                unset($model->pendingResourceLimits);
            }
        });
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
            $result = [];
            foreach (array_keys(ResourceLimit::DEFAULTS) as $key) {
                $result[$key] = $limits->{$key};
            }

            return $result;
        }

        if ($source === 'legacy') {
            $result = [];
            foreach (array_keys(ResourceLimit::DEFAULTS) as $key) {
                $result[$key] = $this->{$key};
            }

            return $result;
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
            $legacyValues = [];
            foreach (array_keys(ResourceLimit::DEFAULTS) as $key) {
                $legacyValues[$key] = $this->{$key};
            }
            $this->resourceLimits()->create($legacyValues);

            // Reset legacy columns to defaults
            $this->update(ResourceLimit::DEFAULTS);

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
