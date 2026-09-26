<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Support\Spaces;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Hash};
use Illuminate\Validation\Rule;
class AdministrationController extends Controller {
 private function admin(Request $r):void{abort_unless($r->user()->role==='admin',403);}
 public function spaces(Request $r){return response()->json(DB::table('workspaces')->join('workspace_user','workspaces.id','=','workspace_user.workspace_id')->where('workspace_user.user_id',$r->user()->id)->select('workspaces.*')->orderBy('workspaces.id')->get());}
 public function saveSpace(Request $r,?int $id=null){
  $this->admin($r);$data=$r->validate(['name'=>'required|string|max:100','color'=>['required',Rule::in(['blue','yellow','green','red','purple','gray'])]]);
  if($id){abort_unless(DB::table('workspace_user')->where('workspace_id',$id)->where('user_id',$r->user()->id)->exists(),404);DB::table('workspaces')->where('id',$id)->update($data+['updated_at'=>now()]);}
  else $id=Spaces::create($r->user(),$data['name'],$data['color']);
  return response()->json(DB::table('workspaces')->find($id));
 }
 public function users(Request $r){
  $this->admin($r);
  return response()->json(User::orderBy('name')->get(['id','name','email','role'])->map(function($u){$u->workspace_ids=DB::table('workspace_user')->where('user_id',$u->id)->pluck('workspace_id');return $u;}));
 }
 public function saveUser(Request $r,?User $user=null){
  $this->admin($r);
  if($user?->exists && $user->role==='admin')abort(403,'La cuenta administradora se mantiene protegida.');
  $data=$r->validate(['name'=>'required|string|max:100','email'=>['required','email','max:255',Rule::unique('users')->ignore($user?->id)],'password'=>[$user?->exists?'nullable':'required','string','min:12','max:200'],'role'=>['required',Rule::in(['editor','reader'])],'workspace_ids'=>'required|array|min:1','workspace_ids.*'=>['integer',Rule::exists('workspace_user','workspace_id')->where('user_id',$r->user()->id)]]);
  $user??=new User;$ids=array_unique($data['workspace_ids']);unset($data['workspace_ids']);
  if(empty($data['password']))unset($data['password']);else $data['password']=Hash::make($data['password']);
  DB::transaction(function()use($user,$data,$ids){$user->fill($data);$user->role=$data['role'];$user->save();DB::table('workspace_user')->where('user_id',$user->id)->delete();foreach($ids as $id)DB::table('workspace_user')->insert(['user_id'=>$user->id,'workspace_id'=>$id]);DB::table('sessions')->where('user_id',$user->id)->delete();});
 return response()->json($user->only('id','name','email','role'));
 }
 public function notificationContacts(Request $r){
  return response()->json(DB::table('notification_contacts')->where('workspace_id',Spaces::id())->orderBy('channel')->orderBy('name')->get());
 }
 public function saveNotificationContact(Request $r,?int $id=null){
  $this->admin($r);
  $data=$r->validate(['name'=>'required|string|max:120','email'=>'required|email|max:255','channel'=>['required',Rule::in(['email','message'])],'is_default'=>'sometimes|boolean']);
  $data['email']=mb_strtolower(trim($data['email']));$data['workspace_id']=Spaces::id();$data['updated_at']=now();
  if($id){$contact=DB::table('notification_contacts')->where('id',$id)->where('workspace_id',Spaces::id())->first();abort_unless($contact,404);DB::table('notification_contacts')->where('id',$id)->update($data);}
  else{$data['created_at']=now();$id=DB::table('notification_contacts')->insertGetId($data);}
  return response()->json(DB::table('notification_contacts')->where('id',$id)->first());
 }
 public function deleteNotificationContact(Request $r,int $id){
  $this->admin($r);abort_unless(DB::table('notification_contacts')->where('id',$id)->where('workspace_id',Spaces::id())->exists(),404);DB::table('notification_contacts')->where('id',$id)->delete();return response()->noContent();
 }
}
