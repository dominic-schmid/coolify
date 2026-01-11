<?php

namespace App\Livewire\Project\Shared;

use App\Models\ResourceLimit;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class ResourceLimits extends Component
{
    use AuthorizesRequests;

    public mixed $resource;

    public ?string $limitsCpus = null;
    public ?string $limitsCpuset = null;
    public ?int $limitsCpuShares = null;
    public ?string $limitsMemory = null;
    public ?string $limitsMemorySwap = null;
    public ?int $limitsMemorySwappiness = null;
    public ?string $limitsMemoryReservation = null;

    public string $limitsSource = 'fresh'; // 'new', 'legacy', or 'fresh'

    protected $rules = [
        'limitsMemory' => 'nullable|string',
        'limitsMemorySwap' => 'nullable|string',
        'limitsMemorySwappiness' => 'nullable|integer|min:0|max:100',
        'limitsMemoryReservation' => 'nullable|string',
        'limitsCpus' => 'nullable|numeric|min:0|max:1024',
        'limitsCpuset' => 'nullable|string',
        'limitsCpuShares' => 'nullable|integer|min:0|max:8192',
    ];

    protected $validationAttributes = [
        'limitsMemory' => 'memory',
        'limitsMemorySwap' => 'swap',
        'limitsMemorySwappiness' => 'swappiness',
        'limitsMemoryReservation' => 'reservation',
        'limitsCpus' => 'cpus',
        'limitsCpuset' => 'cpuset',
        'limitsCpuShares' => 'cpu shares',
    ];

    public function mount()
    {
        $this->loadLimits();
    }

    /**
     * Load resource limits from the appropriate source.
     */
    private function loadLimits(): void
    {
        if (method_exists($this->resource, 'getResourceLimitsSource')) {
            $this->limitsSource = $this->resource->getResourceLimitsSource();

            if ($this->limitsSource === 'new') {
                $this->loadFromNewStructure();
            } elseif ($this->limitsSource === 'legacy') {
                $this->loadFromLegacyColumns();
            } else {
                $this->loadFromFresh();
            }
        } else {
            // Fallback for resources without the trait
            $this->loadFromLegacyColumns();
            $this->limitsSource = 'legacy';
        }
    }

    /**
     * Load limits from the new resource_limits table structure.
     * Converts default values to null for display.
     */
    private function loadFromNewStructure(): void
    {
        $limits = $this->resource->resourceLimits;
        $defaults = ResourceLimit::DEFAULTS;
        $this->limitsCpus = $this->normalizeValueForDisplay($limits->limits_cpus, $defaults['limits_cpus']);
        $this->limitsCpuset = $this->normalizeValueForDisplay($limits->limits_cpuset, $defaults['limits_cpuset']);
        $this->limitsCpuShares = $this->normalizeValueForDisplay($limits->limits_cpu_shares, $defaults['limits_cpu_shares']);
        $this->limitsMemory = $this->normalizeValueForDisplay($limits->limits_memory, $defaults['limits_memory']);
        $this->limitsMemorySwap = $this->normalizeValueForDisplay($limits->limits_memory_swap, $defaults['limits_memory_swap']);
        $this->limitsMemorySwappiness = $this->normalizeValueForDisplay($limits->limits_memory_swappiness, $defaults['limits_memory_swappiness']);
        $this->limitsMemoryReservation = $this->normalizeValueForDisplay($limits->limits_memory_reservation, $defaults['limits_memory_reservation']);
    }

    /**
     * Load limits from direct columns on the resource model (legacy pattern).
     * Converts default values to null for display.
     */
    private function loadFromLegacyColumns(): void
    {
        $defaults = ResourceLimit::DEFAULTS;
        $this->limitsCpus = $this->normalizeValueForDisplay($this->resource->limits_cpus, $defaults['limits_cpus']);
        $this->limitsCpuset = $this->normalizeValueForDisplay($this->resource->limits_cpuset, $defaults['limits_cpuset']);
        $this->limitsCpuShares = $this->normalizeValueForDisplay($this->resource->limits_cpu_shares, $defaults['limits_cpu_shares']);
        $this->limitsMemory = $this->normalizeValueForDisplay($this->resource->limits_memory, $defaults['limits_memory']);
        $this->limitsMemorySwap = $this->normalizeValueForDisplay($this->resource->limits_memory_swap, $defaults['limits_memory_swap']);
        $this->limitsMemorySwappiness = $this->normalizeValueForDisplay($this->resource->limits_memory_swappiness, $defaults['limits_memory_swappiness']);
        $this->limitsMemoryReservation = $this->normalizeValueForDisplay($this->resource->limits_memory_reservation, $defaults['limits_memory_reservation']);
    }

    /**
     * Load limits for a fresh resource (no limits configured yet).
     * Sets all properties to null for display.
     */
    private function loadFromFresh(): void
    {
        $this->limitsCpus = null;
        $this->limitsCpuset = null;
        $this->limitsCpuShares = null;
        $this->limitsMemory = null;
        $this->limitsMemorySwap = null;
        $this->limitsMemorySwappiness = null;
        $this->limitsMemoryReservation = null;
    }

    /**
     * Normalize a value for display: if it equals the default, return null.
     */
    private function normalizeValueForDisplay($value, $default): mixed
    {
        // Compare as strings for consistency
        if ((string) $value === (string) $default) {
            return null;
        }

        return $value;
    }

    /**
     * Normalize a value for saving: if it is null, return the default.
     */
    private function normalizeValueForSave($value, $default): mixed
    {
        return $value === null ? $default : $value;
    }

    /**
     * Normalize properties with defaults before saving.
     * Only applies defaults when value is null (not empty strings).
     */
    private function normalizeProperties(): void
    {
        $defaults = ResourceLimit::DEFAULTS;
        $this->limitsCpus = $this->normalizeValueForSave($this->limitsCpus, $defaults['limits_cpus']);
        $this->limitsCpuset = $this->normalizeValueForSave($this->limitsCpuset, $defaults['limits_cpuset']);
        $this->limitsCpuShares = $this->normalizeValueForSave($this->limitsCpuShares, $defaults['limits_cpu_shares']);
        $this->limitsMemory = $this->normalizeValueForSave($this->limitsMemory, $defaults['limits_memory']);
        $this->limitsMemorySwap = $this->normalizeValueForSave($this->limitsMemorySwap, $defaults['limits_memory_swap']);
        $this->limitsMemorySwappiness = $this->normalizeValueForSave($this->limitsMemorySwappiness, $defaults['limits_memory_swappiness']);
        $this->limitsMemoryReservation = $this->normalizeValueForSave($this->limitsMemoryReservation, $defaults['limits_memory_reservation']);
    }

    private function getLimitsArray(): array
    {
        return [
            'limits_cpus' => $this->limitsCpus,
            'limits_cpuset' => $this->limitsCpuset,
            'limits_cpu_shares' => $this->limitsCpuShares,
            'limits_memory' => $this->limitsMemory,
            'limits_memory_swap' => $this->limitsMemorySwap,
            'limits_memory_swappiness' => $this->limitsMemorySwappiness,
            'limits_memory_reservation' => $this->limitsMemoryReservation,
        ];
    }

    public function submit()
    {
        try {
            $this->authorize('update', $this->resource);

            $this->normalizeProperties();
            $this->validate();
            $this->saveToCurrentStorage();
            $this->updateUIFromStorage();

            $this->dispatch('success', 'Resource limits updated.');
        } catch (\Throwable $e) {
            handleError($e, $this);
        }
    }

    /**
     * Save limits to the correct storage location based on current storage type.
     */
    private function saveToCurrentStorage(): void
    {
        $limits = $this->getLimitsArray();

        if (method_exists($this->resource, 'saveResourceLimits')) {
            $this->resource->saveResourceLimits($limits);
        } else {
            foreach ($limits as $key => $value) {
                $this->resource->{$key} = $value;
            }
            $this->resource->save();
        }
    }

    /**
     * Update UI with values from storage, converting defaults to null for display.
     */
    private function updateUIFromStorage(): void
    {
        // Clear relationship cache to ensure fresh data is loaded
        if (method_exists($this->resource, 'resourceLimits') && $this->resource->relationLoaded('resourceLimits')) {
            $this->resource->unsetRelation('resourceLimits');
        }

        $this->loadLimits();
    }

    /**
     * Migrate legacy resource limits to the new table structure.
     */
    public function migrateToNewStructure()
    {
        try {
            $this->authorize('update', $this->resource);

            if ($this->limitsSource !== 'legacy') {
                $this->dispatch('error', 'This resource is not using legacy storage.');

                return;
            }

            if (!method_exists($this->resource, 'migrateResourceLimitsToNewStructure')) {
                $this->dispatch('error', 'This resource does not support migration.');

                return;
            }

            $success = $this->resource->migrateResourceLimitsToNewStructure();

            if ($success) {
                $this->resource->refresh();
                $this->loadLimits();
                $this->dispatch('success', 'Resource limits migrated to new structure successfully.');
            } else {
                $this->dispatch('error', 'Migration failed. The resource may already be migrated or has no legacy limits.');
            }
        } catch (\Throwable $e) {
            handleError($e, $this);
        }
    }

    /**
     * Check if the current resource uses legacy storage pattern.
     */
    public function isLegacyStorage(): bool
    {
        return $this->limitsSource === 'legacy';
    }

    /**
     * Check if the current resource uses the new storage pattern.
     */
    public function isNewStorage(): bool
    {
        return $this->limitsSource === 'new';
    }

    /**
     * Check if the current resource is fresh (no limits configured yet).
     */
    public function isFreshStorage(): bool
    {
        return $this->limitsSource === 'fresh';
    }

    public function render()
    {
        return view('livewire.project.shared.resource-limits');
    }
}
