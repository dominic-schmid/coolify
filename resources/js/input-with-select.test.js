import test from 'node:test';
import assert from 'node:assert/strict';
import { inputWithSelect } from './input-with-select.js';

const MEMORY_UNITS = ['b', 'k', 'm', 'g'];

/**
 * Build the component outside Alpine, with the $watch that init() registers
 * stubbed out so the split/join logic can be exercised directly.
 *
 * @param {string} entangled
 * @param {string} defaultUnit
 */
function control(entangled, defaultUnit = 'm') {
    const component = inputWithSelect({ defaultUnit, validUnits: MEMORY_UNITS, entangled });
    const watchers = {};

    component.$watch = (property, callback) => {
        watchers[property] = callback;
    };

    component.init();

    return { component, watchers };
}

test('a stored value is split into its number and unit', () => {
    const { component } = control('512m');

    assert.equal(component.value, '512');
    assert.equal(component.unit, 'm');
});

test('picking a unit rejoins into the single value the server stores', () => {
    const { component } = control('512m');

    component.value = '2';
    component.unit = 'g';
    component.commit();

    assert.equal(component.entangled, '2g');
});

test('an unset limit shows an empty field on the default unit', () => {
    for (const stored of ['0', '', null, undefined]) {
        const { component } = control(stored);

        assert.equal(component.value, '', `stored: ${JSON.stringify(stored)}`);
        assert.equal(component.unit, 'm');
    }
});

test('clearing the field commits the unlimited zero rather than a bare unit', () => {
    const { component } = control('512m');

    component.value = '';
    component.commit();

    assert.equal(component.entangled, '0');
});

test('uppercase units from the api or a hand-edited database still match a picker option', () => {
    const { component } = control('256M');

    assert.equal(component.value, '256');
    assert.equal(component.unit, 'm');
});

test('a bare number keeps docker byte semantics instead of being relabelled', () => {
    // Docker reads `mem_limit: 512` as 512 bytes. Defaulting it to MiB would
    // silently raise the limit by a million times on the next save.
    const { component } = control('512');

    assert.equal(component.value, '512');
    assert.equal(component.unit, 'b');
    assert.equal(component.combined, '512b');
});

test('a decimal value survives the round trip', () => {
    const { component } = control('1.5g');

    assert.equal(component.value, '1.5');
    assert.equal(component.unit, 'g');
    assert.equal(component.combined, '1.5g');
});

test('an unparseable stored value stays visible rather than being dropped', () => {
    const { component } = control('not-a-limit');

    assert.equal(component.value, 'not-a-limit');
    assert.equal(component.unit, 'm');
});

test('a value pushed back from the server is re-split into the two controls', () => {
    const { component, watchers } = control('512m');

    component.entangled = '4g';
    watchers.entangled('4g');

    assert.equal(component.value, '4');
    assert.equal(component.unit, 'g');
});

test('the server echoing this control own value does not reset what is being edited', () => {
    const { component, watchers } = control('512m');

    component.value = '2';
    component.unit = 'g';
    component.commit();

    // Livewire round-trips the property; the echo must not clobber the field.
    watchers.entangled('2g');

    assert.equal(component.value, '2');
    assert.equal(component.unit, 'g');
});

test('the number field is never clamped while typing', () => {
    // The old implementation clamped against min/max on every keystroke, which
    // rewrote "1" to the minimum before the user could type "10".
    const { component } = control('0');

    component.value = '1';
    component.commit();

    assert.equal(component.entangled, '1m');
});
