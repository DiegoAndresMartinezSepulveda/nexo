<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tasks', 'production_return_reason')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->text('production_return_reason')->nullable()->after('production_message_notified_at');
                $table->timestamp('production_returned_at')->nullable()->after('production_return_reason');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tasks', 'production_return_reason')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropColumn(['production_return_reason', 'production_returned_at']);
            });
        }
    }
};
