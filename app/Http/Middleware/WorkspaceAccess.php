<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class WorkspaceAccess {
 public function handle(Request $r,Closure $next){
  $membership=DB::table('workspace_user')->where('user_id',$r->user()->id);
  $requested=$r->header('X-Workspace-ID', $r->isMethod('GET')?$r->query('workspace'):null);
  if($requested!==null){abort_unless(ctype_digit((string)$requested),403);$membership->where('workspace_id',(int)$requested);}
  $id=$membership->orderBy('workspace_id')->value('workspace_id');abort_unless($id,403,'No tienes acceso a este espacio.');
  $r->attributes->set('workspace_id',(int)$id);
  if(!in_array($r->method(),['GET','HEAD','OPTIONS']))abort_if($r->user()->role==='reader',403,'Tu cuenta tiene acceso de lectura.');
  return $next($r);
 }
}
