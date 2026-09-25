<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('notify_on_production')->default(false);
            $table->json('notify_emails')->nullable();
            $table->text('notification_message')->nullable();
            $table->timestamp('production_notified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', fn (Blueprint $table) => $table->dropColumn(['notify_on_production', 'notify_emails', 'notification_message', 'production_notified_at']));
    }
};
