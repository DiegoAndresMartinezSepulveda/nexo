<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('entries') && ! Schema::hasColumn('entries', 'visibility')) {
            Schema::table('entries', fn (Blueprint $table) => $table->string('visibility', 20)->default('workspace')->after('diagram'));
        }

        if (! Schema::hasTable('entry_user')) {
            Schema::create('entry_user', function (Blueprint $table) {
                $table->foreignId('entry_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('permission', 10)->default('view');
                $table->primary(['entry_id', 'user_id']);
            });
        }

        if (Schema::hasTable('tasks')) {
            Schema::table('tasks', function (Blueprint $table) {
                if (! Schema::hasColumn('tasks', 'notify_on_production')) {
                    $table->boolean('notify_on_production')->default(false);
                }
                if (! Schema::hasColumn('tasks', 'notify_emails')) {
                    $table->json('notify_emails')->nullable();
                }
                if (! Schema::hasColumn('tasks', 'notification_message')) {
                    $table->text('notification_message')->nullable();
                }
                if (! Schema::hasColumn('tasks', 'notification_fields')) {
                    $table->json('notification_fields')->nullable();
                }
                if (! Schema::hasColumn('tasks', 'production_notified_at')) {
                    $table->timestamp('production_notified_at')->nullable();
                }
                if (! Schema::hasColumn('tasks', 'task_type')) {
                    $table->string('task_type', 20)->default('task');
                }
                if (! Schema::hasColumn('tasks', 'tags')) {
                    $table->json('tags')->nullable();
                }
                if (! Schema::hasColumn('tasks', 'description_blocks')) {
                    $table->json('description_blocks')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // Reparación deliberadamente no destructiva: los datos existentes se conservan.
    }
};
