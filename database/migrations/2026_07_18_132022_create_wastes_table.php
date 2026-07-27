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
        Schema::create('wastes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->decimal('quantity', 10, 2);
            $table->string('unit')->nullable(); // لو عندك multi-unit
            $table->text('reason')->nullable(); // سبب الهلاك (اختياري)
            $table->foreignId('user_id')->constrained(); // مين سجل الهالك
            $table->timestamps();
        });

        Schema::create('waste_item_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('waste_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_item_id')->constrained();
            $table->decimal('quantity', 10, 2); // الكمية المخصومة من الـ batch ده
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wastes');
        Schema::dropIfExists('waste_item_batches');
    }
};
