<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_key')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['family_id', 'attribute_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_attributes');
    }
};
