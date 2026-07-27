<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_date');
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('remaining_stock', 15, 3);
            $table->decimal('avg_cost_price', 15, 3);
            $table->decimal('total_value', 15, 3);
            $table->enum('type', ['monthly', 'daily'])->default('daily');
            $table->timestamps();

            // منتج واحد مايتكررش في نفس اليوم بنفس النوع
            $table->unique(['product_id', 'snapshot_date', 'type'], 'unique_product_snapshot_date_type');

            $table->index(['snapshot_date', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_snapshots');
    }
};
