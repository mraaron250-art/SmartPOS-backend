<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;

return new class {
    public function up(): void
    {
        DB::schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 190)->unique();
            $table->string('password_hash');
            $table->string('role', 10);
            $table->string('status', 10)->default('ACTIVE');
            $table->jsonb('permissions')->default(DB::raw("'[]'::jsonb"));
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('OWNER','ADMIN','STAFF'))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('ACTIVE','INACTIVE'))");
    }

    public function down(): void
    {
        DB::schema()->dropIfExists('users');
    }
};
