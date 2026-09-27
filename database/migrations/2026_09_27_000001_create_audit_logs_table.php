<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            // Snapshots deliberately have no foreign keys: audits must survive
            // deletion or renaming of the actor and workspace.
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->string('workspace_name', 120)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name', 120)->nullable();
            $table->string('actor_email', 255)->nullable();
            $table->string('event', 100);
            $table->string('action', 255);
            $table->string('subject_type', 40)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_name', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['created_at', 'id']);
            $table->index(['workspace_id', 'created_at']);
            $table->index(['actor_id', 'created_at']);
            $table->index(['event', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
