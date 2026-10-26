<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;

return new class {
    public function up(): void
    {
        DB::schema()->create('sales', function (Blueprint $table) {
            $table->id();
            // Idempotency key: a repeated checkout with the same number returns the existing sale
            $table->string('transaction_number', 64)->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('cashier_name', 120);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->string('payment_method', 40)->default('Cash');
            $table->decimal('amount_received', 12, 2);
            $table->decimal('change_amount', 12, 2);
            $table->string('status', 12)->default('COMPLETED');
            $table->boolean('offline_synced')->default(false);
            $table->timestampsTz();

            $table->index('created_at');
            $table->index(['status', 'created_at']);
        });

        DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_status_check CHECK (status IN ('COMPLETED','CANCELLED','REFUNDED'))");

        DB::schema()->create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            // Snapshot at time of sale so receipts never change
            $table->string('product_name', 200);
            $table->string('sku', 64);
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subtotal', 12, 2);

            $table->index('product_id');
        });

        DB::statement('ALTER TABLE sale_items ADD CONSTRAINT sale_items_quantity_positive CHECK (quantity > 0)');
    }

    public function down(): void
    {
        DB::schema()->dropIfExists('sale_items');
        DB::schema()->dropIfExists('sales');
    }
};
