<div>
    <form wire:submit='submit' class="flex flex-col">
        <div class="flex items-center gap-2 ">
            <h2>Resource Limits</h2>
            <x-forms.button canGate="update" :canResource="$resource" type='submit'>Save</x-forms.button>
        </div>
        <div class="">Limit your container resources by CPU & memory.</div>

        @if($this->isLegacyStorage())
            <div class="mt-4 p-4 border rounded-md border-warning bg-warning/10">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-warning flex-shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                    <div class="flex-1">
                        <h4 class="font-semibold text-warning">Legacy Configuration Detected</h4>
                        <p class="text-sm mt-1 dark:text-neutral-300">
                            Resource limits are stored in the old format (direct database columns).
                            We recommend migrating to the new centralized storage for better consistency and future feature
                            support.
                        </p>
                        <p class="text-xs mt-2 dark:text-neutral-400">
                            Migration will copy your current settings to the new structure. Your limits will continue to
                            work normally.
                        </p>
                        @can('update', $resource)
                            <x-forms.button wire:click="migrateToNewStructure" class="mt-3" type="button">
                                Migrate to New Structure
                            </x-forms.button>
                        @endcan
                    </div>
                </div>
            </div>
        @endif

        <h3 class="pt-4">Limit CPUs</h3>
        <div class="flex gap-2">
            <x-forms.input canGate="update" :canResource="$resource" placeholder="1.5"
                helper="0 means use all CPUs. Floating point number, like 0.002 or 1.5. More info <a class='underline dark:text-white' target='_blank' href='https://docs.docker.com/engine/reference/run/#cpu-share-constraint'>here</a>."
                label="Number of CPUs" id="limitsCpus" />
            <x-forms.input canGate="update" :canResource="$resource" placeholder="0-2"
                helper="Empty means, use all CPU sets. 0-2 will use CPU 0, CPU 1 and CPU 2. More info <a class='underline dark:text-white'  target='_blank' href='https://docs.docker.com/engine/reference/run/#cpu-share-constraint'>here</a>."
                label="CPU sets to use" id="limitsCpuset" />
            <x-forms.input canGate="update" :canResource="$resource" placeholder="1024"
                helper="More info <a class='underline dark:text-white' target='_blank' href='https://docs.docker.com/engine/reference/run/#cpu-share-constraint'>here</a>."
                label="CPU Weight" id="limitsCpuShares" />
        </div>
        <h3 class="pt-4">Limit Memory</h3>
        <div class="flex flex-col gap-2">
            <div class="flex gap-2">
                <x-forms.input canGate="update" :canResource="$resource"
                    helper="Examples: 69b (byte) or 420k (kilobyte) or 1337m (megabyte) or 1g (gigabyte).<br>More info <a class='underline dark:text-white' target='_blank' href='https://docs.docker.com/compose/compose-file/05-services/#mem_reservation'>here</a>."
                    label="Soft Memory Limit" id="limitsMemoryReservation" />
                <x-forms.input canGate="update" :canResource="$resource"
                    helper="0-100.<br>More info <a class='underline dark:text-white' target='_blank' href='https://docs.docker.com/compose/compose-file/05-services/#mem_swappiness'>here</a>."
                    type="number" min="0" max="100" label="Swappiness" id="limitsMemorySwappiness" />
            </div>
            <div class="flex gap-2">
                <x-forms.input canGate="update" :canResource="$resource"
                    helper="Examples: 69b (byte) or 420k (kilobyte) or 1337m (megabyte) or 1g (gigabyte).<br>More info <a class='underline dark:text-white' target='_blank' href='https://docs.docker.com/compose/compose-file/05-services/#mem_limit'>here</a>."
                    label="Maximum Memory Limit" id="limitsMemory" />
                <x-forms.input canGate="update" :canResource="$resource"
                    helper="Examples:69b (byte) or 420k (kilobyte) or 1337m (megabyte) or 1g (gigabyte).<br>More info <a class='underline dark:text-white' target='_blank' href='https://docs.docker.com/compose/compose-file/05-services/#memswap_limit'>here</a>."
                    label="Maximum Swap Limit" id="limitsMemorySwap" />
            </div>
        </div>
    </form>
</div>