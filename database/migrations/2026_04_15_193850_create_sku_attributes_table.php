<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sku_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sku_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->restrictOnDelete();
            $table->string('value', 255);
            $table->timestamps();

            $table->unique(['sku_id', 'attribute_id']);
            $table->index(['attribute_id', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sku_attributes');
    }
};
