<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->enum('type', ['central', 'store', 'workshop', 'scrap', 'transit']);
            $table->string('address', 255)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('type');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
