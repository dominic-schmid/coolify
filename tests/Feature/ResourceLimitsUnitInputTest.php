<?php

use App\Livewire\Project\Shared\ResourceLimits;
use App\Models\Application;
use App\Models\Environment;
use App\Models\InstanceSettings;
use App\Models\Project;
use App\Models\Server;
use App\Models\StandaloneDocker;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = Team::factory()->create();
    $this->team->members()->attach($this->user->id, ['role' => 'owner']);

    InstanceSettings::unguarded(function () {
        InstanceSettings::query()->create([
            'id' => 0,
            'is_registration_enabled' => true,
        ]);
    });

    $this->actingAs($this->user);
    session(['currentTeam' => $this->team]);

    // Rendering a form component standalone still hits its @error block.
    View::share('errors', new ViewErrorBag);

    $this->server = Server::factory()->create(['team_id' => $this->team->id]);
    $this->destination = StandaloneDocker::query()->where('server_id', $this->server->id)->firstOrFail();
    $this->project = Project::factory()->create(['team_id' => $this->team->id]);
    $this->environment = Environment::factory()->create(['project_id' => $this->project->id]);
    $this->application = Application::factory()->create([
        'environment_id' => $this->environment->id,
        'destination_id' => $this->destination->id,
        'destination_type' => $this->destination->getMorphClass(),
    ]);

    // The limit columns are database defaults, so re-read them into the model.
    $this->application->refresh();
});

test('input renders a trailing addon when a suffix is given', function () {
    $html = Blade::render('<x-forms.input label="Swappiness" type="number" suffix="%" />');

    expect($html)
        ->toContain('input-group')
        ->toContain('input-group-field')
        ->toContain('class="input-group-addon">%</span>')
        // The unit must be announced with the field, not left as silent decoration.
        ->toMatch('/aria-describedby="([^"]+)"[\s\S]*id="\1" class="input-group-addon"/');
});

test('input renders a trailing addon from a suffix slot', function () {
    $html = Blade::render('<x-forms.input label="CPU limit"><x-slot:suffix>CPUs</x-slot:suffix></x-forms.input>');

    expect($html)
        ->toContain('input-group-addon')
        ->toContain('CPUs');
});

test('input keeps its plain anatomy when no suffix is given', function () {
    $html = Blade::render('<x-forms.input label="CPU set" />');

    expect($html)
        ->not->toContain('input-group')
        ->not->toContain('input-group-addon');
});

test('memory fields render a unit picker bound to the same livewire property', function () {
    $html = Livewire::test(ResourceLimits::class, ['resource' => $this->application])->html();

    // The number half and the unit half are one control over one property.
    expect($html)
        ->toContain('inputWithSelect(')
        ->toContain('input-group-unit')
        ->toContain('listbox-trigger');

    // Each memory field gets its own unit picker (ids carry a per-render suffix).
    foreach (['limitsMemory', 'limitsMemoryReservation', 'limitsMemorySwap'] as $property) {
        expect($html)->toMatch('/'.$property.'-[^"]+-unit-trigger/');
    }

    // Units come from the picker, not from a hand-typed suffix caption.
    expect($html)
        ->toContain('MiB')
        ->toContain('GiB')
        ->not->toContain('Accepted units are');
});

test('memory number fields step in whole units so they cannot produce a value the rules reject', function () {
    // The server rule is /^(0|\d+[bBkKmMgG])$/ — no decimals — and Docker only
    // accepts whole byte counts. A spinner that offers 1.5 would hand the user
    // a value that fails validation on save.
    $html = Livewire::test(ResourceLimits::class, ['resource' => $this->application])->html();

    // x-model="value" is the number half of x-forms.input-with-select.
    preg_match_all('/<input[^>]*x-model="value"[^>]*>/', $html, $matches);

    expect($matches[0])->toHaveCount(3);

    foreach ($matches[0] as $field) {
        expect($field)->toContain('step="1"')->toContain('min="0"');
    }
});

test('cpu and swappiness fields keep plain inputs with static suffixes', function () {
    $html = Livewire::test(ResourceLimits::class, ['resource' => $this->application])->html();

    expect($html)
        ->toContain('>CPUs</span>')
        ->toContain('>%</span>');
});

test('memory values submitted with a unit are stored as one docker string', function () {
    Livewire::test(ResourceLimits::class, ['resource' => $this->application])
        ->set('limitsMemory', '512m')
        ->set('limitsMemoryReservation', '256m')
        ->set('limitsMemorySwap', '2g')
        ->set('limitsMemorySwappiness', 80)
        ->set('limitsCpus', '1.5')
        ->call('submit')
        ->assertHasNoErrors();

    $this->application->refresh();

    expect($this->application->limits_memory)->toBe('512m')
        ->and($this->application->limits_memory_reservation)->toBe('256m')
        ->and($this->application->limits_memory_swap)->toBe('2g')
        ->and($this->application->limits_memory_swappiness)->toBe(80)
        ->and($this->application->limits_cpus)->toBe('1.5');
});

test('an empty memory field falls back to the unlimited zero value', function () {
    Livewire::test(ResourceLimits::class, ['resource' => $this->application])
        ->set('limitsMemory', '')
        ->call('submit')
        ->assertHasNoErrors();

    $this->application->refresh();

    expect($this->application->limits_memory)->toBe('0');
});
