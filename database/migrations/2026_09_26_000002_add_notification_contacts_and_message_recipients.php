<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('email', 255);
            $table->string('channel', 20)->default('email');
            $table->boolean('is_default')->default(true);
            $table->timestamps();
            $table->unique(['workspace_id', 'channel', 'email']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('notify_message_on_production')->default(false);
            $table->json('notify_message_emails')->nullable();
            $table->text('notification_message_short')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['notify_message_on_production', 'notify_message_emails', 'notification_message_short']);
        });
        Schema::dropIfExists('notification_contacts');
    }
};
