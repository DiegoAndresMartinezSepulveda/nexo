<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tasks') && ! Schema::hasColumn('tasks', 'estimated_delivery_at')) {
            Schema::table('tasks', fn (Blueprint $table) => $table->dateTime('estimated_delivery_at')->nullable()->after('due_date'));
        }
    }

    public function down(): void
    {
        // Preserve production data if this migration is rolled back.
    }
};
