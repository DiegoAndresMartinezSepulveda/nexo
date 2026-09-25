<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
return new class extends Migration {
    public function up(): void {
        Schema::table('tasks', fn(Blueprint $t) => $t->boolean('is_fire')->default(false));
        DB::table('tasks')->where('environment','certification')->update(['status'=>'review']);
        DB::table('tasks')->whereIn('environment',['qa','production'])->whereNotIn('status',['review','done'])->update(['status'=>'review']);
    }
    public function down(): void { Schema::table('tasks', fn(Blueprint $t) => $t->dropColumn('is_fire')); }
};
