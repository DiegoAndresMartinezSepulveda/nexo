<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('workspaces') && ! Schema::hasColumn('workspaces', 'workflow')) {
            Schema::table('workspaces', fn (Blueprint $table) => $table->json('workflow')->nullable()->after('color'));
        }
    }

    public function down(): void
    {
        // La configuración contiene decisiones del usuario; no se elimina automáticamente.
    }
};
