<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;

return new class {
    public function up(): void
    {
        DB::schema()->create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('product_name', 200);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name', 120);
            $table->string('transaction_type', 20);
            $table->integer('quantity'); // signed stock delta
            $table->integer('previous_stock');
            $table->integer('new_stock');
            $table->string('reference_id', 64)->default('');
            $table->text('reason')->default('');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['product_id', 'created_at']);
            $table->index('transaction_type');
            $table->index('created_at');
        });

        DB::statement("ALTER TABLE inventory_transactions ADD CONSTRAINT inventory_tx_type_check CHECK (transaction_type IN ('SALE','STOCK_IN','STOCK_OUT','STOCK_ADJUSTMENT'))");
    }

    public function down(): void
    {
        DB::schema()->dropIfExists('inventory_transactions');
    }
};
