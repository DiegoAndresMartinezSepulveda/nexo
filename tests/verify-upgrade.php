<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:']);
Illuminate\Support\Facades\DB::purge('sqlite');
use Illuminate\Support\Facades\{Artisan,DB};
$paths=['database/migrations/0001_01_01_000000_create_users_table.php','database/migrations/0001_01_01_000001_create_cache_table.php','database/migrations/0001_01_01_000002_create_jobs_table.php','database/migrations/2026_09_23_000001_create_tasks_table.php'];
if(Artisan::call('migrate',['--path'=>$paths,'--force'=>true])!==0)throw new RuntimeException(Artisan::output());
$id=DB::table('users')->insertGetId(['name'=>'Cuenta previa','email'=>'upgrade@example.test','password'=>bcrypt('TestOnlyUpgrade123'),'created_at'=>now(),'updated_at'=>now()]);
$task=DB::table('tasks')->insertGetId(['user_id'=>$id,'title'=>'Ñandú · certificación','environment'=>'certification','status'=>'development','checklist'=>json_encode([['text'=>'Subir SQL','done'=>true]]),'created_at'=>now(),'updated_at'=>now()]);
DB::table('attachments')->insert(['task_id'=>$task,'name'=>'requisitos.txt','path'=>'attachments/test.txt','size'=>3,'created_at'=>now(),'updated_at'=>now()]);
if(Artisan::call('migrate',['--force'=>true])!==0)throw new RuntimeException(Artisan::output());
$t=DB::table('tasks')->find($task);
if($t->status!=='review'||$t->title!=='Ñandú · certificación'||!$t->workspace_id||DB::table('attachments')->count()!==1||DB::table('users')->find($id)->role!=='admin'||DB::table('workspaces')->count()!==3||DB::table('clients')->count()!==11)throw new RuntimeException('Upgrade invariants failed');
echo "UPGRADE OK: datos, adjuntos, tildes, espacios y administrador conservados.\n";
