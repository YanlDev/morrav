<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movements', function (Blueprint $table) {
            $table->foreignId('origin_warehouse_id')
                ->nullable()
                ->after('reference_id')
                ->constrained('warehouses')
                ->nullOnDelete();

            $table->foreignId('destination_warehouse_id')
                ->nullable()
                ->after('origin_warehouse_id')
                ->constrained('warehouses')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('destination_warehouse_id');
            $table->dropConstrainedForeignId('origin_warehouse_id');
        });
    }
};
