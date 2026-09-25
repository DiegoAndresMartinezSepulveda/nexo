<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('tasks', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('title'); $t->text('description')->nullable();
            $t->string('status')->default('pending'); $t->string('environment')->default('local');
            $t->string('priority')->default('normal'); $t->date('due_date')->nullable();
            $t->json('checklist')->nullable(); $t->timestamps(); $t->index(['user_id', 'status']);
        });
        Schema::create('attachments', function (Blueprint $t) {
            $t->id(); $t->foreignId('task_id')->constrained()->cascadeOnDelete();
            $t->string('name'); $t->string('path'); $t->unsignedBigInteger('size'); $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('attachments'); Schema::dropIfExists('tasks'); }
};
