<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;

return new class {
    public function up(): void
    {
        DB::schema()->create('store_settings', function (Blueprint $table) {
            $table->id();
            $table->string('store_name', 150);
            $table->string('address', 255)->default('');
            $table->string('phone', 50)->default('');
            $table->string('currency_symbol', 8)->default('₱');
            $table->decimal('tax_rate_percent', 5, 2)->default(12);
            $table->string('receipt_header', 255)->default('');
            $table->string('receipt_footer', 255)->default('');
            $table->timestampTz('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        DB::schema()->dropIfExists('store_settings');
    }
};
