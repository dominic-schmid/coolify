<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('resource_limits', function (Blueprint $table) {
            $table->id();
            $table->morphs('resource'); // resource_type, resource_id

            // CPU limits
            $table->string('limits_cpus')->default('0');
            $table->string('limits_cpuset')->nullable();
            $table->integer('limits_cpu_shares')->default(1024);

            // Memory limits
            $table->string('limits_memory')->default('0');
            $table->string('limits_memory_swap')->default('0');
            $table->integer('limits_memory_swappiness')->default(60);
            $table->string('limits_memory_reservation')->default('0');

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
