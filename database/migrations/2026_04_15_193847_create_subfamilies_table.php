<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subfamilies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['family_id', 'code']);
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subfamilies');
    }
};
