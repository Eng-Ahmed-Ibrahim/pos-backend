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
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')
                ->constrained('suppliers')
                ->onDelete("cascade");
            
            $table->decimal("total",10,2)->default(0);
            $table->enum('status', [
                'pending',      // لم يتم الدفع
                'partial',      // دفع جزء
                'paid',         // مدفوع بالكامل
                'cancelled',    // ملغي
                'refunded',     // تم استرجاع المبلغ
                'rejected'      // مرفوض
            ])->default('pending');
            $table->string('image')->nullable();
            $table->date('date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
