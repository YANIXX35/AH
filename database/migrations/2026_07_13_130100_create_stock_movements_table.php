<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('stock_products')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20);
            $table->decimal('quantity', 15, 2);
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->decimal('quantity_after', 15, 2);
            $table->decimal('average_cost_after', 15, 2);
            $table->date('movement_date');
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'movement_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
