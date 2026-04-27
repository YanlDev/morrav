<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('internal_code', 20)->unique();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->foreignId('family_id')->constrained()->restrictOnDelete();
            $table->foreignId('subfamily_id')->nullable()->constrained()->nullOnDelete();
            $table->string('brand', 100)->nullable();
            $table->enum('unit_of_measure', ['unit', 'meter', 'kg', 'set', 'pair', 'box']);
            $table->boolean('is_temporary')->default(false);
            $table->date('temporary_end_date')->nullable();
            $table->enum('status', ['draft', 'active', 'discontinued'])->default('draft');
            $table->string('main_photo')->nullable();
            $table->string('fingerprint', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('fingerprint');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
