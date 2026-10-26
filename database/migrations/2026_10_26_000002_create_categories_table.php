<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;

return new class {
    public function up(): void
    {
        DB::schema()->create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->text('description')->default('');
            $table->string('status', 10)->default('ACTIVE');
            $table->timestampsTz();
        });

        // Category names are unique regardless of letter case
        DB::statement('CREATE UNIQUE INDEX categories_name_lower_unique ON categories (LOWER(name))');
        DB::statement("ALTER TABLE categories ADD CONSTRAINT categories_status_check CHECK (status IN ('ACTIVE','INACTIVE'))");
    }

    public function down(): void
    {
        DB::schema()->dropIfExists('categories');
    }
};
