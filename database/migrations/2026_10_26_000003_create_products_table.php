<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;

return new class {
    public function up(): void
    {
        DB::schema()->create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('category_name', 120)->default('General');
            $table->string('sku', 64)->unique();
            $table->string('barcode', 64)->nullable()->unique();
            $table->string('name', 200);
            $table->text('description')->default('');
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('selling_price', 12, 2);
            $table->integer('stock_quantity')->default(0);
            $table->integer('reorder_level')->default(10);
            $table->string('status', 10)->default('ACTIVE');
            $table->timestampsTz();

            $table->index('name');
            $table->index('status');
        });

        // Last line of defence against overselling
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_stock_non_negative CHECK (stock_quantity >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_prices_non_negative CHECK (cost_price >= 0 AND selling_price >= 0)');
        DB::statement("ALTER TABLE products ADD CONSTRAINT products_status_check CHECK (status IN ('ACTIVE','INACTIVE'))");
    }

    public function down(): void
    {
        DB::schema()->dropIfExists('products');
    }
};
