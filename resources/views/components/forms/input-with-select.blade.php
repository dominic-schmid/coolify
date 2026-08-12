@php
    $inputId = $htmlId.'-input';
    $unitId = $htmlId.'-unit';
@endphp

{{--
    The unit change arrives as a bubbling `listbox-change`. Reading the unit off
    the event (rather than waiting for x-model to settle) keeps commit() reading
    the unit the user just picked.
--}}
<div class="w-full min-w-0"
    x-data="inputWithSelect({
        defaultUnit: @js($defaultOption ?? ''),
        validUnits: @js(array_keys($options)),
        @if ($modelBinding !== 'null')
            entangled: @entangle($modelBinding),
        @else
            entangled: @js($value ?? '0'),
        @endif
    })"
    @listbox-change="unit = $event.detail.value; commit()">
    @if ($label)
        {{-- Same fixed-height label row as x-forms.input so both align side by side. --}}
        <div class="mb-1.5 flex h-4 w-full items-center gap-1.5">
            <label for="{{ $inputId }}" class="mb-0! flex items-center gap-1 text-sm font-medium leading-4">
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

    <div class="input-group">
        {{-- Carries the combined value so wire:dirty flags this control like a plain input. --}}
        @if ($modelBinding !== 'null')
            <input type="hidden" wire:model="{{ $modelBinding }}" wire:dirty.class="is-dirty" />
        @endif

        <input {{ $attributes->merge(['class' => $defaultClass.' input-group-field']) }} type="{{ $type }}"
            id="{{ $inputId }}" x-model="value" @input="commit()" @disabled($disabled) @readonly($readonly)
            wire:loading.attr="disabled" placeholder="{{ $placeholder }}" autocomplete="{{ $autocomplete }}"
            name="{{ $name }}" @if ($min !== null) min="{{ $min }}" @endif
            @if ($max !== null) max="{{ $max }}" @endif @if ($step !== null) step="{{ $step }}" @endif
            @if ($autofocus) x-ref="autofocusInput" @endif>

        <div class="input-group-unit">
            <x-forms.listbox :id="$unitId" :htmlId="$unitId" :wire="false" :value="$defaultOption"
                :options="$listboxOptions()" :disabled="$disabled" :tooltip="false" x-model="unit" />
        </div>
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
