<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('damage_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sku_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->string('reason_code', 20)->nullable();
            $table->string('reason_notes', 255)->nullable();
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('reported_at');
            $table->foreignId('movement_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('repair_order_line_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index('sku_id');
            $table->index('warehouse_id');
            $table->index('reported_by');
            $table->index('reported_at');
            $table->index(['sku_id', 'repair_order_line_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('damage_reports');
    }
};
