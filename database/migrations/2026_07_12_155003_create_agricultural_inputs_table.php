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
        Schema::create('agricultural_inputs', function (Blueprint $table) {
            $table->id();
            $table->date('purchase_date');
            $table->string('input');
            $table->string('supplier');
            $table->decimal('qty', 10, 2);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total', 12, 2);
            $table->date('expiry')->nullable();
            $table->date('used_on')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agricultural_inputs');
    }
};
