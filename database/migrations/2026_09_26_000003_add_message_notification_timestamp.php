<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('tasks', 'production_message_notified_at')) {
            Schema::table('tasks', fn (Blueprint $table) => $table->timestamp('production_message_notified_at')->nullable()->after('production_notified_at'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tasks', 'production_message_notified_at')) {
            Schema::table('tasks', fn (Blueprint $table) => $table->dropColumn('production_message_notified_at'));
        }
    }
};
