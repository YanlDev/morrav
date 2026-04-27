<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sku_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_claimed', 12, 2);
            $table->decimal('quantity_repaired', 12, 2)->nullable();
            $table->decimal('quantity_scrapped', 12, 2)->nullable();
            $table->foreignId('destination_warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index('repair_order_id');
            $table->index('sku_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_order_lines');
    }
};
