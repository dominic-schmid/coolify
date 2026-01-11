<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Column names match Docker Compose service-level resource limits.
     * See: https://docs.docker.com/compose/compose-file/compose-file-v3/#resources
     */
    public function up(): void
    {
        Schema::create('resource_limits', function (Blueprint $table) {
            $table->id();
            $table->morphs('resource'); // resource_type, resource_id

            // CPU limits (currently used)
            $table->decimal('cpus', 7, 3)->nullable(); // supports up to 9999.999 with 0.001 precision
            $table->string('cpuset')->nullable();
            $table->integer('cpu_shares')->nullable();

            // Memory limits (currently used)
            $table->string('mem_limit')->nullable();
            $table->string('memswap_limit')->nullable();
            $table->integer('mem_swappiness')->nullable();
            $table->string('mem_reservation')->nullable();

            // Process limits (not yet used)
            $table->integer('pids_limit')->nullable();

            // OOM Killer (not yet used)
            $table->boolean('oom_kill_disable')->nullable();

            // Block I/O limits (not yet used)
            $table->integer('blkio_weight')->nullable();

            // Ulimits (not yet used - complex nested structure)
            $table->json('ulimits')->nullable();

            // Disk limits (not yet used - stored only, no docker-compose enforcement)
            $table->bigInteger('disk_mb')->nullable();

            $table->timestamps();

            $table->unique(['resource_type', 'resource_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resource_limits');
    }
};
