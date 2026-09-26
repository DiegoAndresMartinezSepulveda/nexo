<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tasks', 'environment_return_from')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->string('environment_return_from', 30)->nullable()->after('production_returned_at');
            });
        }
        if (! Schema::hasColumn('tasks', 'environment_return_to')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->string('environment_return_to', 30)->nullable()->after('environment_return_from');
            });
        }
        if (! Schema::hasColumn('tasks', 'environment_return_reason')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->text('environment_return_reason')->nullable()->after('environment_return_to');
            });
        }
        if (! Schema::hasColumn('tasks', 'environment_return_solution')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->text('environment_return_solution')->nullable()->after('environment_return_reason');
            });
        }
        if (! Schema::hasColumn('tasks', 'environment_return_resolved')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->boolean('environment_return_resolved')->default(false)->after('environment_return_solution');
            });
        }
        if (! Schema::hasColumn('tasks', 'environment_returned_at')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->timestamp('environment_returned_at')->nullable()->after('environment_return_resolved');
            });
        }

        if (Schema::hasColumn('tasks', 'production_return_reason')) {
            $legacy = DB::table('tasks')
                ->whereNotNull('production_return_reason')
                ->whereNull('environment_return_reason')
                ->get(['id', 'production_return_reason', 'production_returned_at']);

            foreach ($legacy as $task) {
                DB::table('tasks')->where('id', $task->id)->update([
                    'environment_return_from' => 'production',
                    'environment_return_to' => 'development',
                    'environment_return_reason' => $task->production_return_reason,
                    'environment_returned_at' => $task->production_returned_at,
                ]);
            }
        }
    }

    public function down(): void
    {
        foreach (['environment_return_from', 'environment_return_to', 'environment_return_reason', 'environment_return_solution', 'environment_return_resolved', 'environment_returned_at'] as $column) {
            if (Schema::hasColumn('tasks', $column)) {
                Schema::table('tasks', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
