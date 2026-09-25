<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->string('visibility', 20)->default('workspace')->after('diagram');
        });

        Schema::create('entry_user', function (Blueprint $table) {
            $table->foreignId('entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('permission', 10)->default('view');
            $table->primary(['entry_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_user');
        Schema::table('entries', fn (Blueprint $table) => $table->dropColumn('visibility'));
    }
};
