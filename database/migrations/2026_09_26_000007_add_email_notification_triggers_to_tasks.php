<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tasks', 'email_notification_events')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->json('email_notification_events')->nullable();
            });
        }

        if (! Schema::hasColumn('tasks', 'environment_entered_at')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->timestamp('environment_entered_at')->nullable();
            });
        }

        if (! Schema::hasColumn('tasks', 'environment_notification_notified_at')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->timestamp('environment_notification_notified_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['email_notification_events', 'environment_entered_at', 'environment_notification_notified_at'] as $column) {
            if (Schema::hasColumn('tasks', $column)) {
                Schema::table('tasks', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
