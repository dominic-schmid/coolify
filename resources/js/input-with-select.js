/**
 * Alpine data provider for `x-forms.input-with-select`.
 *
 * The control shows a plain number next to a unit picker, but the Livewire
 * property behind it stores a single Docker-style string ("256m", "2g", "0").
 * This component owns that split/join so the server keeps seeing one value.
 *
 * @param {{defaultUnit: string, validUnits: string[], entangled: string}} config
 */
export function inputWithSelect({ defaultUnit, validUnits, entangled }) {
    return {
        value: '',
        unit: defaultUnit,
        entangled,

        init() {
            this.applyCombined(this.entangled);

            // Livewire can replace the value from the server (validation
            // failure, resource refresh). Re-split it unless it is simply
            // echoing what this control just wrote.
            this.$watch('entangled', (incoming) => {
                if (incoming !== this.combined) {
                    this.applyCombined(incoming);
                }
            });
        },

        /**
         * The single string the Livewire property stores.
         *
         * @returns {string}
         */
        get combined() {
            if (this.value === '' || this.value === null || this.value === undefined) {
                return '0';
            }

            return `${this.value}${this.unit}`;
        },

        commit() {
            this.entangled = this.combined;
        },

        /**
         * Split a stored value back into its number and unit parts.
         *
         * Deliberately does not clamp against min/max: rewriting the field
         * mid-edit fights anyone typing a longer number one digit at a time.
         * The browser attributes and server rules handle the bounds.
         *
         * @param {string|null} raw
         */
        applyCombined(raw) {
            if (raw === null || raw === undefined || raw === '' || raw === '0') {
                this.value = '';
                this.unit = defaultUnit;

                return;
            }

            const stored = String(raw).trim();
            // Longest suffix first so a multi-character unit cannot be
            // shadowed by a single-character one that ends the same way.
            const units = [...validUnits].sort((a, b) => b.length - a.length);
            const lowered = stored.toLowerCase();

            for (const unit of units) {
                // Docker accepts "512M" as well as "512m"; normalise to the
                // stored casing so the picker still matches an option.
                if (!lowered.endsWith(unit.toLowerCase())) {
                    continue;
                }

                const numericPart = stored.slice(0, -unit.length);

                if (numericPart !== '' && !Number.isNaN(Number(numericPart))) {
                    this.value = numericPart;
                    this.unit = unit;

                    return;
                }
            }

            // A bare number means bytes to Docker, so keep it as bytes when
            // that unit is offered — re-labelling it as the default unit
            // would silently inflate the stored limit.
            const byteUnit = validUnits.find((unit) => unit.toLowerCase() === 'b');

            if (byteUnit !== undefined && !Number.isNaN(Number(stored))) {
                this.value = stored;
                this.unit = byteUnit;

                return;
            }

            // Unrecognised value: keep it visible and editable rather than
            // silently dropping what the user already had stored.
            this.value = stored;
            this.unit = defaultUnit;
        },
    };
}

/**
 * Registering from the bundle (rather than an inline <script>) means the
 * component is defined before Alpine boots and stays defined across
 * `wire:navigate`, where an `alpine:init` listener added by a freshly
 * swapped-in page would never fire.
 */
export function initializeInputWithSelectComponent() {
    window.Alpine.data('inputWithSelect', inputWithSelect);
}
