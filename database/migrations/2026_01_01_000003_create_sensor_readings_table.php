<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensor_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->float('temperature')->nullable();
            $table->float('humidity')->nullable();
            $table->float('soil_moisture')->nullable();
            $table->unsignedInteger('water_level_cm')->nullable();
            $table->unsignedInteger('gas_level')->nullable();
            $table->unsignedInteger('rain')->nullable();
            $table->boolean('motion')->nullable();
            $table->json('raw_payload');
            $table->timestamp('recorded_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['device_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensor_readings');
    }
};
