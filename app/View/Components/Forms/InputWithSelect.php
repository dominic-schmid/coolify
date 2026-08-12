<?php

namespace App\View\Components\Forms;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\Component;

/**
 * A number field glued to a unit picker, bound to a single Livewire property.
 *
 * The property keeps storing one Docker-style string ("256m", "2g", "0") — the
 * split into number and unit is client-side only, so validation rules and model
 * columns are unchanged.
 */
class InputWithSelect extends Component
{
    public ?string $modelBinding = null;

    public ?string $htmlId = null;

    /**
     * @param  array<string, string>  $options  Stored unit => label shown in the picker, e.g. ['m' => 'MiB'].
     */
    public function __construct(
        public ?string $id = null,
        public ?string $name = null,
        public ?string $type = 'number',
        public ?string $value = null,
        public ?string $label = null,
        public array $options = [],
        public ?string $defaultOption = null,
        public bool $required = false,
        public bool $disabled = false,
        public bool $readonly = false,
        public ?string $helper = null,
        public ?string $placeholder = null,
        public string $defaultClass = 'input',
        public string $autocomplete = 'off',
        public ?string $min = null,
        public ?string $max = null,
        public ?string $step = null,
        public bool $autofocus = false,
        public ?string $canGate = null,
        public mixed $canResource = null,
        public bool $autoDisable = true,
    ) {
        // Handle authorization-based disabling
        if ($this->canGate && $this->canResource && $this->autoDisable) {
            $hasPermission = Gate::allows($this->canGate, $this->canResource);

            if (! $hasPermission) {
                $this->disabled = true;
            }
        }
    }

    /**
     * The unit picker options in the shape `x-forms.listbox` expects.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function listboxOptions(): array
    {
        $options = [];

        foreach ($this->options as $unit => $label) {
            $options[] = ['value' => (string) $unit, 'label' => (string) $label];
        }

        return $options;
    }

    public function render(): View|Closure|string
    {
        // Store original ID for wire:model binding (property name)
        $this->modelBinding = $this->id;

        if (is_null($this->id)) {
            $this->id = new_public_id();
            // Don't create wire:model binding for auto-generated IDs
            $this->modelBinding = 'null';
        }
        // Generate unique HTML ID by adding random suffix
        // This prevents duplicate IDs when multiple forms are on the same page
        if ($this->modelBinding && $this->modelBinding !== 'null') {
            // Use original ID with random suffix for uniqueness
            $uniqueSuffix = new_public_id();
            $this->htmlId = $this->modelBinding.'-'.$uniqueSuffix;
        } else {
            $this->htmlId = (string) $this->id;
        }

        if (is_null($this->name)) {
            $this->name = $this->modelBinding !== 'null' ? $this->modelBinding : (string) $this->id;
        }

        if (is_null($this->defaultOption) && ! empty($this->options)) {
            $this->defaultOption = array_key_first($this->options);
        }

        return view('components.forms.input-with-select');
    }
}
