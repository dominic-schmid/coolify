<div>
    <form wire:submit='submit' class="flex flex-col gap-1">
        <div class="flex items-center gap-2">
            <h2>Resource Limits</h2>
            <x-forms.button canGate="update" :canResource="$resource" type='submit'>Save</x-forms.button>
        </div>
        <p>Limit your container resources by CPU & memory.</p>
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
                            Resource limits are stored in the old format.
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
        <div class="flex flex-col gap-3 pt-4">
            <h3>Limit CPUs</h3>
            <div class="flex flex-col gap-4">
                <div class="flex flex-col md:flex-row gap-4">
                    <x-forms.input canGate="update" :canResource="$resource" type="number" min="0" max="1024" step="0.1"
                        placeholder="0"
                        helper="Limit how much CPU the container can use. 0 means unlimited (use all available CPUs). Use decimal numbers like 1.5 for one and a half CPUs, or 0.5 for half a CPU.<br>More info <a class='underline dark:text-white' target='_blank' href='https://docs.docker.com/engine/reference/run/#cpu-quota-constraint'>cpu-quota</a>."
                        label="CPU Limit" id="limitsCpus">
                        <x-slot:suffix>
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M5 5m0 1a1 1 0 0 1 1 -1h12a1 1 0 0 1 1 1v12a1 1 0 0 1 -1 1h-12a1 1 0 0 1 -1 -1z" />
                                <path d="M9 9h6v6h-6z" />
                                <path d="M9 1v3" />
                                <path d="M15 1v3" />
                                <path d="M9 20v3" />
                                <path d="M15 20v3" />
                                <path d="M20 9h3" />
                                <path d="M20 14h3" />
                                <path d="M1 9h3" />
                                <path d="M1 14h3" />
                                <path d="M12 9v6" />
                                <path d="M9 12h6" />
                            </svg>
                        </x-slot:suffix>
                    </x-forms.input>
                </div>
                <div class="flex flex-col md:flex-row gap-4">
                    <x-forms.input canGate="update" :canResource="$resource" placeholder="0"
                        helper="Pin container to specific CPU threads. 0 means use all threads. Example: 0-1,4 results in using threads 0,1,4.<br>More info <a class='underline dark:text-white'  target='_blank' href='https://docs.docker.com/engine/reference/run/#cpuset-constraint'>cpuset</a>."
                        label="CPU sets to use" id="limitsCpuset">
                        <x-slot:suffix>
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M9 4a3 3 0 0 1 6 0c0 1.657 -1.343 3 -3 3s-3 -1.343 -3 -3" />
                                <path d="M12 7v13" />
                                <path d="M9 20h6" />
                            </svg>
                        </x-slot:suffix>
                    </x-forms.input>
                    <x-forms.input canGate="update" :canResource="$resource" type="number" min="0" max="8192" step="64"
                        placeholder="1024"
                        helper="Relative CPU priority when containers compete for resources. Default: 1024 (normal). Examples: 512 = half priority, 2048 = double priority.<br>More info <a class='underline dark:text-white' target='_blank' href='https://docs.docker.com/engine/reference/run/#cpu-share-constraint'>cpu_shares</a>."
                        label="CPU Weight" id="limitsCpuShares">
                        <x-slot:suffix>
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M5 5m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                <path d="M19 5m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                <path d="M5 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                <path d="M19 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                <path d="M5 7l0 10" />
                                <path d="M19 7l0 10" />
                                <path d="M7 5l10 0" />
                                <path d="M7 19l10 0" />
                            </svg>
                        </x-slot:suffix>
                    </x-forms.input>
                </div>
            </div>
        </div>
        <div class="flex flex-col gap-3 pt-4">
            <h3>Limit Memory</h3>
            <div class="flex flex-col gap-4">
                <div class="flex flex-col md:flex-row gap-4">
                    <x-forms.input-with-select canGate="update" :canResource="$resource"
                        type="number"
                        min="0"
                        placeholder="0"
                        helper="Hard limit on container memory usage. The container will be killed if it exceeds this limit.<br>More info <a class='underline dark:text-white' target='_blank' href='https://docs.docker.com/compose/compose-file/05-services/#mem_limit'>mem_limit</a>."
                        label="Memory Limit" id="limitsMemory"
                        :options="['b' => 'B', 'k' => 'KiB', 'm' => 'MiB', 'g' => 'GiB']"
                        defaultOption="m" />
                    <x-forms.input-with-select canGate="update" :canResource="$resource"
                        type="number"
                        min="0"
                        placeholder="0"
                        helper="Guaranteed memory reservation for the container. Docker attempts to ensure this amount is always available.<br>More info <a class='underline dark:text-white' target='_blank' href='https://docs.docker.com/compose/compose-file/05-services/#mem_reservation'>mem_reservation</a>."
                        label="Memory Reservation" id="limitsMemoryReservation"
                        :options="['b' => 'B', 'k' => 'KiB', 'm' => 'MiB', 'g' => 'GiB']"
                        defaultOption="m" />
                </div>
                <div class="flex flex-col md:flex-row gap-4">
                    <x-forms.input-with-select canGate="update" :canResource="$resource"
                        type="number"
                        min="0"
                        placeholder="0"
                        helper="Total limit for memory plus swap space. Combined limit for both RAM and swap usage.<br>More info <a class='underline dark:text-white' target='_blank' href='https://docs.docker.com/compose/compose-file/05-services/#memswap_limit'>memswap_limit</a>."
                        label="Maximum Swap Limit" id="limitsMemorySwap"
                        :options="['b' => 'B', 'k' => 'KiB', 'm' => 'MiB', 'g' => 'GiB']"
                        defaultOption="m" />
                    <x-forms.input canGate="update" :canResource="$resource"
                        placeholder="60"
                        helper="Control how aggressively the kernel swaps memory. 0 = swap only when necessary, 100 = swap aggressively. Default: 60.<br>More info <a class='underline dark:text-white' target='_blank' href='https://docs.docker.com/compose/compose-file/05-services/#mem_swappiness'>mem_swappiness</a>."
                        type="number" min="0" max="100" label="Swappiness"
                        id="limitsMemorySwappiness" suffix="%" />
                </div>
            </div>
        </div>
    </form>
</div>