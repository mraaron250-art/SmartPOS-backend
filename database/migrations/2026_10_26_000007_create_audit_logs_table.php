<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;

return new class {
    public function up(): void
    {
        // Append-only: the application never updates or deletes audit rows
        DB::schema()->create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name', 120)->default('System');
            $table->string('action', 100);
            $table->string('module', 50);
            $table->string('target_type', 50)->default('');
            $table->string('target_id', 100)->nullable();
            $table->string('result', 10)->default('SUCCESS');
            $table->text('details')->default('');
            $table->timestampTz('created_at')->useCurrent();

            $table->index('created_at');
            $table->index(['module', 'created_at']);
            $table->index('action');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        DB::schema()->dropIfExists('audit_logs');
    }
};
