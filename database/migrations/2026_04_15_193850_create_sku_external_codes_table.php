<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sku_external_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sku_id')->constrained()->cascadeOnDelete();
            $table->string('code', 100);
            $table->enum('type', ['barcode', 'supplier', 'legacy']);
            $table->string('supplier', 100)->nullable();
            $table->timestamps();

            $table->unique(['code', 'type']);
            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sku_external_codes');
    }
};
