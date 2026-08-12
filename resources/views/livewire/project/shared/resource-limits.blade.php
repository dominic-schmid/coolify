@php
    $memoryUnits = ['b' => 'B', 'k' => 'KiB', 'm' => 'MiB', 'g' => 'GiB'];
@endphp

<form wire:submit="submit" class="application-settings-form flex flex-col gap-6">
    <x-unsaved-bar action="submit" />

    <x-application.settings-section id="cpu-limits-section" title="CPU"
        helper="Limit CPU capacity, affinity, and scheduling priority for this container.">
        <x-slot:actions>
            <a class="button" target="_blank" rel="noopener noreferrer"
                href="https://docs.docker.com/engine/containers/resource_constraints/#cpu">
                Docker CPU constraints
                <x-reicon name="external-link" class="size-3.5" />
            </a>
        </x-slot:actions>
        <div class="grid gap-4 md:grid-cols-3">
            <x-forms.input canGate="update" :canResource="$resource" placeholder="1.5"
                helper="Set to 0 to use all available CPUs. Decimal values such as 0.5 are supported."
                label="CPU limit" id="limitsCpus" suffix="CPUs" />
            <x-forms.input canGate="update" :canResource="$resource" placeholder="0-2"
                helper="Restrict execution to specific cores, for example 0-2 or 0,1,3. Leave empty for all cores."
                label="CPU set" id="limitsCpuset" />
            <x-forms.input canGate="update" :canResource="$resource" placeholder="1024"
                helper="Relative CPU scheduling weight. Docker uses 1024 by default."
                label="CPU weight" id="limitsCpuShares" />
        </div>
    </x-application.settings-section>

    <x-application.settings-section id="memory-limits-section" title="Memory"
        helper="Set hard and soft memory limits, swap allowance, and swappiness.">
        <div class="grid gap-4 sm:grid-cols-2">
            <x-forms.input-with-select canGate="update" :canResource="$resource" min="0" step="1" placeholder="256"
                helper="Soft reservation used when the host is under memory pressure. Leave empty for no reservation."
                label="Memory reservation" id="limitsMemoryReservation" :options="$memoryUnits" defaultOption="m" />
            <x-forms.input-with-select canGate="update" :canResource="$resource" min="0" step="1" placeholder="1"
                helper="Maximum memory available to the container. Leave empty for unlimited."
                label="Memory limit" id="limitsMemory" :options="$memoryUnits" defaultOption="g" />
            <x-forms.input-with-select canGate="update" :canResource="$resource" min="0" step="1" placeholder="2"
                helper="Combined memory and swap allowance. Leave empty for unlimited."
                label="Memory and swap limit" id="limitsMemorySwap" :options="$memoryUnits" defaultOption="g" />
            <x-forms.input canGate="update" :canResource="$resource"
                helper="Controls how aggressively anonymous memory is swapped. Enter a value from 0 to 100."
                type="number" min="0" max="100" label="Swappiness" id="limitsMemorySwappiness" suffix="%" />
        </div>
    </x-application.settings-section>
</form>
