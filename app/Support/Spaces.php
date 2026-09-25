<?php
namespace App\Support;
use App\Models\User;
use Illuminate\Support\Facades\DB;
class Spaces {
 public static function create(User $user,string $name='Mi espacio',string $color='blue',bool $seedClients=false):int {
  return DB::transaction(function()use($user,$name,$color,$seedClients){
   $id=DB::table('workspaces')->insertGetId(['owner_id'=>$user->id,'name'=>$name,'color'=>$color,'created_at'=>now(),'updated_at'=>now()]);
   DB::table('workspace_user')->insert(['workspace_id'=>$id,'user_id'=>$user->id]);
   if($seedClients)foreach(['FEN','HDI','DIPRECA','JUNJI','LAS CONDES','CSSO','CEN','ESMAX','VITACURA','UACH','DEMO'] as $name)DB::table('clients')->insert(['user_id'=>$user->id,'workspace_id'=>$id,'name'=>$name,'created_at'=>now(),'updated_at'=>now()]);
   return $id;
  });
 }
 public static function id():int {return (int)request()->attributes->get('workspace_id');}
}
