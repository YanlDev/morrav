<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('internal_code', 20)->unique();
            $table->string('variant_name', 150)->nullable();
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->decimal('purchase_price', 12, 2)->nullable();
            $table->string('photo')->nullable();
            $table->enum('status', ['draft', 'active', 'discontinued'])->default('draft');
            $table->string('fingerprint', 64)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('fingerprint');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skus');
    }
};
