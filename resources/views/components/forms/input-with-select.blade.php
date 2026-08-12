@php
    $inputId = $htmlId !== 'null' ? $htmlId.'-input' : null;
    $selectId = $htmlId !== 'null' ? $htmlId.'-select' : null;
@endphp

<div class="w-full"
    x-data="inputWithSelect({
        defaultUnit: @js($defaultOption ?? ''),
        min: @js($min),
        max: @js($max),
        validUnits: @js(array_keys($options)),
        @if ($modelBinding !== 'null')
            entangled: @entangle($combinedBinding),
        @else
            entangled: @js($value ?? '0'),
        @endif
    })"
    x-ref="container">
    @if ($label)
        <div class="mb-1.5 flex h-4 w-full items-center gap-1.5">
            <label @if ($inputId) for="{{ $inputId }}" @endif class="mb-0! flex items-center gap-1 text-sm font-medium leading-4">
                {{ $label }}
                @if ($required)
                    <x-highlighted text="*" />
                @endif
            </label>
            @if ($helper)
                <x-helper :helper="$helper" />
            @endif
        </div>
    @endif

    <div class="flex input-with-select-container">
        {{-- Hidden input carries the combined value so wire:dirty tracking works like a normal field --}}
        @if ($modelBinding !== 'null')
            <input type="hidden" wire:model={{ $combinedBinding }} wire:dirty.class="dirty-tracker" />
        @endif

        <input type="{{ $type }}" @if ($inputId) id="{{ $inputId }}" @endif x-model="value" @blur="commit()"
            @disabled($disabled) @readonly($readonly) placeholder="{{ $placeholder }}"
            autocomplete="{{ $autocomplete }}" name="{{ $name }}-input"
            @if ($min !== null) min="{{ $min }}" @endif
            @if ($max !== null) max="{{ $max }}" @endif
            minlength="{{ $minlength }}" maxlength="{{ $maxlength }}"
            class="{{ $defaultClass }} flex-1 rounded-r-none border-r-0"
            @if ($autofocus) x-ref="autofocusInput" @endif
            aria-label="{{ $label }}" x-ref="input">

        <select @if ($selectId) id="{{ $selectId }}" @endif x-model="unit" @change="commit()" @disabled($disabled)
            name="{{ $name }}-select" class="select w-auto min-w-[70px] rounded-l-none border-l-0"
            aria-label="{{ $label }} unit">
            @foreach ($options as $key => $display)
                <option value="{{ $key }}">{{ $display }}</option>
            @endforeach
        </select>
    </div>

    @if (!$label && $helper)
        <x-helper :helper="$helper" />
    @endif
    @error($modelBinding)
        <label class="label">
            <span class="text-red-500 label-text-alt">{{ $message }}</span>
        </label>
    @enderror
</div>

@once
    <style>
        /* Reflect the hidden dirty-tracker input's dirty state onto the visible number input */
        .input-with-select-container input[type="hidden"].dirty-tracker~input {
            box-shadow: inset 4px 0 0 #6b16ed, inset 0 0 0 2px #e5e5e5 !important;
        }

        .dark .input-with-select-container input[type="hidden"].dirty-tracker~input {
            box-shadow: inset 4px 0 0 #fcd452, inset 0 0 0 2px #242424 !important;
        }
    </style>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('inputWithSelect', ({ defaultUnit, validUnits, entangled }) => ({
                value: '',
                unit: defaultUnit,
                entangled: entangled,

                init() {
                    this.fromCombined(this.entangled);

                    this.$watch('entangled', (newVal) => {
                        if (newVal !== this.combined) {
                            this.fromCombined(newVal);
                        }
                    });
                },

                get combined() {
                    return this.value ? this.value + this.unit : '0';
                },

                commit() {
                    this.entangled = this.combined;
                },

                fromCombined(raw) {
                    if (!raw || raw === '0' || raw === 'null' || raw === null) {
                        this.value = '';
                        this.unit = defaultUnit;
                        return;
                    }

                    const units = [...validUnits].sort((a, b) => b.length - a.length);
                    for (const unit of units) {
                        if (raw.endsWith(unit)) {
                            const numericPart = raw.slice(0, -unit.length);
                            if (numericPart) {
                                this.value = numericPart;
                                this.unit = unit;
                                return;
                            }
                        }
                    }

                    this.value = raw;
                    this.unit = defaultUnit;
                },
            }));
        });
    </script>
@endonce
