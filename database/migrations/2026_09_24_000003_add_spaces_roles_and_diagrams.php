<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration {
 public function up():void {
  Schema::table('users',fn(Blueprint $t)=>$t->string('role',20)->default('editor'));
  Schema::create('workspaces',function(Blueprint $t){$t->id();$t->foreignId('owner_id')->constrained('users')->cascadeOnDelete();$t->string('name',100);$t->string('color',20)->default('blue');$t->timestamps();});
  Schema::create('workspace_user',function(Blueprint $t){$t->foreignId('workspace_id')->constrained()->cascadeOnDelete();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->primary(['workspace_id','user_id']);});
  foreach(['clients','projects','tasks','entries','media','activities'] as $table) Schema::table($table,fn(Blueprint $t)=>$t->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete());
  foreach(DB::table('users')->orderBy('id')->get() as $user){
   foreach(['Safin'=>'blue','Sodimac'=>'yellow','Personal'=>'purple'] as $name=>$color){
    $wid=DB::table('workspaces')->insertGetId(['owner_id'=>$user->id,'name'=>$name,'color'=>$color,'created_at'=>now(),'updated_at'=>now()]);
    DB::table('workspace_user')->insert(['workspace_id'=>$wid,'user_id'=>$user->id]);
    if($name==='Safin')foreach(['clients','projects','tasks','entries','media','activities'] as $table)DB::table($table)->where('user_id',$user->id)->update(['workspace_id'=>$wid]);
   }
  }
  $first=DB::table('users')->min('id');if($first)DB::table('users')->where('id',$first)->update(['role'=>'admin']);
  Schema::table('clients',fn(Blueprint $t)=>$t->index('user_id','clients_user_lookup'));
  Schema::table('clients',function(Blueprint $t){$t->dropUnique(['user_id','name']);$t->unique(['workspace_id','name']);$t->string('code',40)->nullable();});
  Schema::table('entries',fn(Blueprint $t)=>$t->json('diagram')->nullable());
 }
 public function down():void {
  Schema::table('entries',fn(Blueprint $t)=>$t->dropColumn('diagram'));
  Schema::table('clients',function(Blueprint $t){$t->dropUnique(['workspace_id','name']);$t->dropColumn('code');});
  foreach(['activities','media','entries','tasks','projects','clients'] as $table)Schema::table($table,fn(Blueprint $t)=>$t->dropConstrainedForeignId('workspace_id'));
  Schema::dropIfExists('workspace_user');Schema::dropIfExists('workspaces');Schema::table('users',fn(Blueprint $t)=>$t->dropColumn('role'));
 }
};
