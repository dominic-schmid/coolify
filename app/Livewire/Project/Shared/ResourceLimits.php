<?php

namespace App\Livewire\Project\Shared;

use App\Models\ResourceLimit;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class ResourceLimits extends Component
{
    use AuthorizesRequests;

    public mixed $resource;

    // NOTE: In a future version string support will be removed
    public string|float|null $limitsCpus = null;
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
     */
    private function loadFromNewStructure(): void
    {
        $limits = $this->resource->resourceLimits;
        $this->limitsCpus = $limits->cpus;
        $this->limitsCpuset = $limits->cpuset;
        $this->limitsCpuShares = $limits->cpu_shares;
        $this->limitsMemory = $limits->mem_limit;
        $this->limitsMemorySwap = $limits->memswap_limit;
        $this->limitsMemorySwappiness = $limits->mem_swappiness;
        $this->limitsMemoryReservation = $limits->mem_reservation;
    }

    /**
     * Load limits from direct columns on the resource model (legacy pattern).
     * Normalizes '0' memory values to '0m' for UI compatibility.
     */
    private function loadFromLegacyColumns(): void
    {
        $this->limitsCpus = $this->resource->limits_cpus;
        $this->limitsCpuset = $this->resource->limits_cpuset;
        $this->limitsCpuShares = $this->resource->limits_cpu_shares;

        // Normalize '0' to '0m' for memory fields so UI can handle them
        $this->limitsMemory = $this->normalizeMemoryValue($this->resource->limits_memory);
        $this->limitsMemorySwap = $this->normalizeMemoryValue($this->resource->limits_memory_swap);
        $this->limitsMemorySwappiness = $this->resource->limits_memory_swappiness;
        $this->limitsMemoryReservation = $this->normalizeMemoryValue($this->resource->limits_memory_reservation);
    }

    /**
     * Normalize memory value for UI display.
     * Converts '0' to '0m' so the input-with-select component can handle it.
     */
    private function normalizeMemoryValue(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // If value is just '0' without a unit, add 'm' so UI can parse it
        if ($value === '0') {
            return '0m';
        }

        return $value;
    }

    /**
     * Load limits for a fresh resource (no limits configured yet).
     * All properties are null.
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

    private function getLimitsArray(): array
    {
        // Return array with new docker-compose column names
        // The trait will handle mapping to legacy names if needed
        return [
            'cpus' => $this->limitsCpus,
            'cpuset' => $this->limitsCpuset,
            'cpu_shares' => $this->limitsCpuShares,
            'mem_limit' => $this->limitsMemory,
            'memswap_limit' => $this->limitsMemorySwap,
            'mem_swappiness' => $this->limitsMemorySwappiness,
            'mem_reservation' => $this->limitsMemoryReservation,
        ];
    }

    public function submit()
    {
        try {
            $this->authorize('update', $this->resource);

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
     * Update UI with values from storage.
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
