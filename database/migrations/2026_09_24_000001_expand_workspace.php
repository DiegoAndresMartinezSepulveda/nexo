<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
return new class extends Migration {
    public function up(): void {
        Schema::create('clients', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete(); $t->string('name', 100); $t->timestamps(); $t->unique(['user_id', 'name']);
        });
        Schema::create('projects', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete(); $t->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name', 150); $t->text('description')->nullable(); $t->timestamps();
        });
        Schema::table('tasks', function (Blueprint $t) {
            $t->foreignId('client_id')->nullable()->constrained()->nullOnDelete(); $t->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $t->json('notes_blocks')->nullable(); $t->text('sql_notes')->nullable();
        });
        Schema::create('entries', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete(); $t->string('kind', 20);
            $t->string('title', 180); $t->json('blocks')->nullable(); $t->text('search_text')->nullable();
            $t->string('color', 20)->default('yellow'); $t->boolean('pinned')->default(false); $t->boolean('archived')->default(false);
            $t->foreignId('client_id')->nullable()->constrained()->nullOnDelete(); $t->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $t->string('category', 100)->nullable(); $t->json('tags')->nullable(); $t->timestamps(); $t->index(['user_id', 'kind', 'archived']);
        });
        Schema::create('media', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('task_id')->nullable()->constrained()->cascadeOnDelete(); $t->foreignId('entry_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('name'); $t->string('path'); $t->string('mime', 120); $t->unsignedBigInteger('size'); $t->timestamps();
        });
        Schema::create('activities', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete(); $t->string('action'); $t->string('subject_type', 30);
            $t->unsignedBigInteger('subject_id')->nullable(); $t->string('title'); $t->timestamps(); $t->index(['user_id','created_at']);
        });
        foreach (DB::table('users')->select('id')->cursor() as $user) {
            foreach (['FEN','HDI','DIPRECA','JUNJI','LAS CONDES','CSSO','CEN','ESMAX','VITACURA','UACH','DEMO'] as $name) {
                DB::table('clients')->insert(['user_id' => $user->id, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }
    public function down(): void {
        Schema::dropIfExists('activities'); Schema::dropIfExists('media'); Schema::dropIfExists('entries');
        Schema::table('tasks', function (Blueprint $t) { $t->dropConstrainedForeignId('project_id'); $t->dropConstrainedForeignId('client_id'); $t->dropColumn(['notes_blocks','sql_notes']); });
        Schema::dropIfExists('projects'); Schema::dropIfExists('clients');
    }
};
