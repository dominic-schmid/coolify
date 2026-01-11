<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\ResourceLimit;
use App\Models\Service;
use App\Models\ServiceApplication;
use App\Models\ServiceDatabase;
use App\Models\StandaloneClickhouse;
use App\Models\StandaloneDragonfly;
use App\Models\StandaloneKeydb;
use App\Models\StandaloneMariadb;
use App\Models\StandaloneMongodb;
use App\Models\StandaloneMysql;
use App\Models\StandalonePostgresql;
use App\Models\StandaloneRedis;
use App\Traits\HasResourceLimits;
use Illuminate\Console\Command;

class MigrateResourceLimits extends Command
{
    protected $signature = 'resource-limits:migrate {--execute} {--type=} {--id=}';

    protected $description = 'Migrate legacy resource limits to new structure';

    public function handle(): int
    {
        $isDryRun = !$this->option('execute');
        $resourceType = $this->option('type');
        $resourceId = $this->option('id');

        $this->printHeader($isDryRun);

        if ($resourceId && !$resourceType) {
            $this->error('--id option requires --type option');

            return 1;
        }

        $models = $this->getResourceModels($resourceType);
        $results = [];

        foreach ($models as $modelClass => $modelName) {
            $legacyResources = $this->findLegacyResources($modelClass, $resourceId);

            if ($legacyResources->isEmpty()) {
                continue;
            }

            $results[$modelName] = [
                'model' => $modelClass,
                'resources' => $legacyResources,
            ];

            $this->printResourceGroup($modelName, $legacyResources, $isDryRun);

            if (!$isDryRun) {
                $this->migrateResources($legacyResources);
            }
        }

        if (empty($results)) {
            $this->info('No resources with legacy limits found.');

            return 0;
        }

        $this->printSummary($results, $isDryRun);

        return 0;
    }

    private function printHeader(bool $isDryRun): void
    {
        $this->line('');
        $this->line('Resource Limits Migration');
        $this->line('=========================');
        $this->line('');

        if ($isDryRun) {
            $this->info('Mode: DRY RUN (no changes will be made)');
            $this->line('Use --execute flag to perform actual migration.');
        } else {
            $this->warn('Mode: EXECUTE (changes will be made)');
        }

        $this->line('');
        $this->line('Scanning resources...');
        $this->line('');
    }

    private function getResourceModels(?string $filterType): array
    {
        $allModels = [
            Application::class => 'Application',
            Service::class => 'Service',
            ServiceApplication::class => 'ServiceApplication',
            ServiceDatabase::class => 'ServiceDatabase',
            StandalonePostgresql::class => 'StandalonePostgresql',
            StandaloneRedis::class => 'StandaloneRedis',
            StandaloneMysql::class => 'StandaloneMysql',
            StandaloneMariadb::class => 'StandaloneMariadb',
            StandaloneMongodb::class => 'StandaloneMongodb',
            StandaloneClickhouse::class => 'StandaloneClickhouse',
            StandaloneKeydb::class => 'StandaloneKeydb',
            StandaloneDragonfly::class => 'StandaloneDragonfly',
        ];

        if ($filterType) {
            $filtered = array_filter($allModels, fn ($name) => $name === $filterType);

            if (empty($filtered)) {
                $this->error("Unknown resource type: {$filterType}");
                $this->line('Available types: '.implode(', ', array_values($allModels)));

                return [];
            }

            return $filtered;
        }

        return $allModels;
    }

    private function findLegacyResources(string $modelClass, ?int $resourceId)
    {
        $query = $modelClass::query();

        if ($resourceId) {
            $query->where('id', $resourceId);
        }

        return $query->get()->filter(fn ($resource) => $resource->hasLegacyResourceLimits());
    }

    private function printResourceGroup(string $modelName, $resources, bool $isDryRun): void
    {
        $count = $resources->count();
        $this->line("[{$modelName}] {$count} resource".($count !== 1 ? 's' : '').' found with legacy limits');
        $this->line('');

        foreach ($resources as $resource) {
            $this->printResourceInfo($resource, $isDryRun);
        }

        $this->line('');
    }

    private function printResourceInfo($resource, bool $isDryRun): void
    {
        $name = $this->getResourceName($resource);
        $this->line("  ID: {$resource->id} | Name: {$name}");
        $this->line('  '.str_repeat('-', 50));

        $limitsTransfer = $this->formatLimitsTransfer($resource);
        if (!empty($limitsTransfer)) {
            $this->line('  Limits to migrate:');
            foreach ($limitsTransfer as $line) {
                $this->line("    {$line}");
            }
        } else {
            $this->line('  No limits to migrate (all are defaults)');
        }

        $this->line('');
    }

    private function getResourceName($resource): string
    {
        if (isset($resource->name)) {
            return $resource->name;
        }

        if (isset($resource->uuid)) {
            return $resource->uuid;
        }

        return 'N/A';
    }

    private function formatLimitsTransfer($resource): array
    {
        $legacyToNew = ResourceLimit::getLegacyToNewMapping();
        $legacyDefaults = ResourceLimit::getLegacyDefaults();
        $lines = [];

        foreach ($legacyToNew as $legacyKey => $newKey) {
            $currentValue = $resource->{$legacyKey};
            $defaultValue = $legacyDefaults[$legacyKey] ?? null;

            if ($this->isLegacyDefaultValue($currentValue, $defaultValue)) {
                continue;
            }

            $newValue = $currentValue;

            if ($newKey === 'cpus' && $newValue !== null) {
                $newValue = (float) $newValue;
            }

            $displayValue = $newValue ?? 'null';
            $defaultNote = '';

            if ($newKey === 'cpus' && $newValue !== null) {
                $displayValue = number_format((float) $newValue, 3, '.', '');
            }

            $lines[] = "{$newKey}: {$currentValue} -> {$displayValue}";
        }

        return $lines;
    }

    private function isLegacyDefaultValue($currentValue, $defaultValue): bool
    {
        if ($currentValue === null && $defaultValue === null) {
            return true;
        }

        if ($currentValue === null || $defaultValue === null) {
            return false;
        }

        return trim((string) $currentValue) === trim((string) $defaultValue);
    }

    private function migrateResources($resources): void
    {
        $successCount = 0;
        $failCount = 0;

        foreach ($resources as $resource) {
            try {
                $result = $resource->migrateResourceLimitsToNewStructure();
                if ($result) {
                    $successCount++;
                } else {
                    $failCount++;
                }
            } catch (\Exception $e) {
                $this->error("  Failed to migrate resource ID {$resource->id}: {$e->getMessage()}");
                $failCount++;
            }
        }

        if ($successCount > 0) {
            $this->info("  Migrated {$successCount} resource".($successCount !== 1 ? 's' : '').' successfully');
        }

        if ($failCount > 0) {
            $this->warn("  {$failCount} resource".($failCount !== 1 ? 's' : '').' could not be migrated');
        }
    }

    private function printSummary(array $results, bool $isDryRun): void
    {
        $this->line('Summary');
        $this->line('-------');

        $total = 0;
        foreach ($results as $modelName => $data) {
            $count = $data['resources']->count();
            $total += $count;
            $this->line("  - {$modelName}: {$count}");
        }

        $this->line('');
        $this->line("Total resources to migrate: {$total}");

        if ($isDryRun) {
            $this->line('');
            $this->line('Run with --execute flag to perform migration.');
        } else {
            $this->line('');
            $this->info('Migration completed.');
        }
    }
}
