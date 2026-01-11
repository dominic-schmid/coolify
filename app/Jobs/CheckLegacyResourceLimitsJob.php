<?php

namespace App\Jobs;

use App\Traits\HasResourceLimits;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckLegacyResourceLimitsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $legacyCounts = HasResourceLimits::countAllLegacyResources();

        if (empty($legacyCounts)) {
            return;
        }

        $total = array_sum($legacyCounts);
        $details = collect($legacyCounts)
            ->map(fn ($count, $model) => "  - {$model}: {$count}")
            ->implode("\n");

        $message = "Deprecated resource limits storage detected.\n";
        $message .= "Found {$total} resource".($total !== 1 ? 's' : '')." still using legacy limits_* columns:\n";
        $message .= "{$details}\n";
        $message .= "Run 'php artisan resource-limits:migrate' to view details.\n";
        $message .= "Run 'php artisan resource-limits:migrate --execute' to migrate.\n";
        $message .= "Legacy storage will be removed in a future version.";

        Log::warning($message);
    }
}
