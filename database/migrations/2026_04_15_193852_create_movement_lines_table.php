<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sku_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->enum('direction', ['in', 'out']);
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['sku_id', 'warehouse_id']);
            $table->index(['warehouse_id', 'direction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movement_lines');
    }
};
