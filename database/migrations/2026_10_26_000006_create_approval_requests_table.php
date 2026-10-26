<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;

return new class {
    public function up(): void
    {
        DB::schema()->create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('requester_name', 120);
            $table->string('action', 100);
            $table->string('module', 50);
            $table->string('target_type', 50)->default('');
            $table->string('target_id', 100)->nullable();
            $table->text('reason')->default('');
            $table->string('status', 12)->default('PENDING');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reviewer_name', 120)->nullable();
            $table->text('review_comment')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('reviewed_at')->nullable();

            $table->index(['status', 'created_at']);
        });

        DB::statement("ALTER TABLE approval_requests ADD CONSTRAINT approval_status_check CHECK (status IN ('PENDING','APPROVED','REJECTED','CANCELLED'))");
    }

    public function down(): void
    {
        DB::schema()->dropIfExists('approval_requests');
    }
};
