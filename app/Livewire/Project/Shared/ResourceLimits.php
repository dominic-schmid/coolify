<?php

namespace App\Livewire\Project\Shared;

use App\Models\ResourceLimit;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class ResourceLimits extends Component
{
    use AuthorizesRequests;

    // Default values for resource limits
    private const DEFAULT_CPU_LIMIT = 0.0;
    private const DEFAULT_CPU_SET = '0';
    private const DEFAULT_CPU_SHARES = 1024;
    private const DEFAULT_MEMORY_SWAPPINESS = 60;
    private const DEFAULT_MEMORY_LIMIT = '0';
    private const DEFAULT_MEMORY_SWAP = '0';
    private const DEFAULT_MEMORY_RESERVATION = '0';

    public $resource;

    // Explicit properties for form binding
    public ?string $limitsCpus = null;

    public ?string $limitsCpuset = null;
    public ?int $limitsCpuShares = null;
    public ?string $limitsMemory = null;
    public ?string $limitsMemorySwap = null;
    public ?int $limitsMemorySwappiness = null;
    public ?string $limitsMemoryReservation = null;

    // Storage pattern tracking
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
        // Check if the resource uses the HasResourceLimits trait
        if (method_exists($this->resource, 'getResourceLimitsSource')) {
            $this->limitsSource = $this->resource->getResourceLimitsSource();

            if ($this->limitsSource === 'new') {
                // Load from resource_limits table
                $limits = $this->resource->resourceLimits;
                $this->limitsCpus = $limits->limits_cpus;
                $this->limitsCpuset = $limits->limits_cpuset;
                $this->limitsCpuShares = $limits->limits_cpu_shares;
                $this->limitsMemory = $limits->limits_memory;
                $this->limitsMemorySwap = $limits->limits_memory_swap;
                $this->limitsMemorySwappiness = $limits->limits_memory_swappiness;
                $this->limitsMemoryReservation = $limits->limits_memory_reservation;
            } elseif ($this->limitsSource === 'legacy') {
                // Load from direct columns (legacy pattern)
                $this->syncFromLegacyColumns();
            } else {
                // Fresh resource - use defaults
                $this->applyDefaults();
            }
        } else {
            // Fallback for resources without the trait (shouldn't happen, but safe)
            $this->syncFromLegacyColumns();
            $this->limitsSource = 'legacy';
        }
    }

    /**
     * Sync properties from legacy direct columns on the resource.
     */
    private function syncFromLegacyColumns(): void
    {
        $this->limitsCpus = $this->resource->limits_cpus;
        $this->limitsCpuset = $this->resource->limits_cpuset;
        $this->limitsCpuShares = $this->resource->limits_cpu_shares;
        $this->limitsMemory = $this->resource->limits_memory;
        $this->limitsMemorySwap = $this->resource->limits_memory_swap;
        $this->limitsMemorySwappiness = $this->resource->limits_memory_swappiness;
        $this->limitsMemoryReservation = $this->resource->limits_memory_reservation;
    }

    /**
     * Apply default values to properties.
     */
    private function applyDefaults(): void
    {
        $this->limitsCpus = ResourceLimit::DEFAULTS['limits_cpus'];
        $this->limitsCpuset = ResourceLimit::DEFAULTS['limits_cpuset'];
        $this->limitsCpuShares = ResourceLimit::DEFAULTS['limits_cpu_shares'];
        $this->limitsMemory = ResourceLimit::DEFAULTS['limits_memory'];
        $this->limitsMemorySwap = ResourceLimit::DEFAULTS['limits_memory_swap'];
        $this->limitsMemorySwappiness = ResourceLimit::DEFAULTS['limits_memory_swappiness'];
        $this->limitsMemoryReservation = ResourceLimit::DEFAULTS['limits_memory_reservation'];
    }

    /**
     * Normalize properties with defaults before saving.
     */
    private function normalizeProperties(): void
    {
        if (empty($this->limitsMemory)) {
            $this->limitsMemory = self::DEFAULT_MEMORY_LIMIT;
        }
        if (empty($this->limitsMemorySwap)) {
            $this->limitsMemorySwap = self::DEFAULT_MEMORY_SWAP;
        }
        if (empty($this->limitsMemoryReservation)) {
            $this->limitsMemoryReservation = self::DEFAULT_MEMORY_RESERVATION;
        }
        if ($this->limitsCpus === null) {
            $this->limitsCpus = self::DEFAULT_CPU_LIMIT;
        }
        if (empty($this->limitsCpuset)) {
            $this->limitsCpuset = self::DEFAULT_CPU_SET;
        }
        if ($this->limitsCpuShares === null) {
            $this->limitsCpuShares = self::DEFAULT_CPU_SHARES;
        }
        if ($this->limitsMemorySwappiness === null) {
            $this->limitsMemorySwappiness = self::DEFAULT_MEMORY_SWAPPINESS;
        }
    }

    /**
     * Get the current property values as an array.
     */
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

            if (method_exists($this->resource, 'saveResourceLimits')) {
                // Use the trait's save method (handles new/legacy/fresh automatically)
                $this->resource->saveResourceLimits($this->getLimitsArray());

                // Refresh source tracking
                $this->limitsSource = $this->resource->getResourceLimitsSource();
            } else {
                // Fallback to legacy direct column save
                $this->resource->limits_cpus = $this->limitsCpus;
                $this->resource->limits_cpuset = $this->limitsCpuset;
                $this->resource->limits_cpu_shares = $this->limitsCpuShares;
                $this->resource->limits_memory = $this->limitsMemory;
                $this->resource->limits_memory_swap = $this->limitsMemorySwap;
                $this->resource->limits_memory_swappiness = $this->limitsMemorySwappiness;
                $this->resource->limits_memory_reservation = $this->limitsMemoryReservation;
                $this->resource->save();
            }

            $this->dispatch('success', 'Resource limits updated.');
        } catch (\Throwable $e) {
            handleError($e, $this);
        }
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
                // Reload the resource and limits
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
