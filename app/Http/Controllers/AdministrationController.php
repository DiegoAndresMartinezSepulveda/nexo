<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use App\Support\AuditLogger;
use App\Support\Spaces;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Hash,Log,Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;
class AdministrationController extends Controller {
 private function admin(Request $r):void{abort_unless($r->user()->role==='admin',403);}
 public function spaces(Request $r){return response()->json(DB::table('workspaces')->join('workspace_user','workspaces.id','=','workspace_user.workspace_id')->where('workspace_user.user_id',$r->user()->id)->select('workspaces.*')->orderBy('workspaces.id')->get());}
 public function saveSpace(Request $r,?int $id=null){
  $this->admin($r);$data=$r->validate(['name'=>'required|string|max:100','color'=>['required',Rule::in(['blue','yellow','green','red','purple','gray'])]]);
  $created=!$id;
  if($id){abort_unless(DB::table('workspace_user')->where('workspace_id',$id)->where('user_id',$r->user()->id)->exists(),404);DB::table('workspaces')->where('id',$id)->update($data+['updated_at'=>now()]);}
  else $id=Spaces::create($r->user(),$data['name'],$data['color']);
  AuditLogger::record($r,'admin.workspace.'.($created?'created':'updated'),$created?'Espacio creado':'Espacio actualizado','workspace',(int)$id,$data['name'],(int)$id,['fields'=>['name']]);
  return response()->json(DB::table('workspaces')->find($id));
 }
 public function users(Request $r){
  $this->admin($r);
  return response()->json(User::orderBy('name')->get(['id','name','email','role','profile_photo_path'])->map(function($u){$u->workspace_ids=DB::table('workspace_user')->where('user_id',$u->id)->pluck('workspace_id');return $u->profilePayload()+['workspace_ids'=>$u->workspace_ids];}));
 }
 public function saveUser(Request $r,?User $user=null){
  $this->admin($r);
  if($user?->exists && $user->role==='admin')abort(403,'La cuenta administradora se mantiene protegida.');
  $data=$r->validate(['name'=>'required|string|max:100','email'=>['required','email','max:255',Rule::unique('users')->ignore($user?->id)],'password'=>[$user?->exists?'nullable':'required','string','min:12','max:200'],'role'=>['required',Rule::in(['editor','reader'])],'workspace_ids'=>'required|array|min:1','workspace_ids.*'=>['integer',Rule::exists('workspace_user','workspace_id')->where('user_id',$r->user()->id)]]);
  $user??=new User;$existing=$user->exists;$passwordChanged=$existing&&!empty($data['password']);$ids=array_unique($data['workspace_ids']);unset($data['workspace_ids']);
  $passwordHash=empty($data['password'])?null:Hash::make($data['password']);unset($data['password']);
  DB::transaction(function()use($user,$data,$ids,$existing,$passwordHash){$nextVersion=(int)$user->auth_version+($existing?1:0);$user->fill($data);if($passwordHash)$user->forceFill(['password'=>$passwordHash,'remember_token'=>Str::random(60)]);if($existing)$user->forceFill(['auth_version'=>$nextVersion]);$user->role=$data['role'];$user->save();DB::table('workspace_user')->where('user_id',$user->id)->delete();foreach($ids as $id)DB::table('workspace_user')->insert(['user_id'=>$user->id,'workspace_id'=>$id]);DB::table('sessions')->where('user_id',$user->id)->delete();if($existing)$user->tokens()->delete();});
  AuditLogger::record($r,'admin.user.'.($existing?'updated':'created'),$existing?'Usuario actualizado':'Usuario creado','user',(int)$user->id,$user->name,null,['fields'=>['name','role','workspace_ids',...($passwordChanged?['password']:[])],'role'=>$user->role,'workspace_ids'=>$ids]);
  if($passwordChanged){try{$user->notify(new PasswordChangedNotification);}catch(Throwable $exception){Log::warning('Nexo password change notice could not be sent after an administrator update.', ['user_id'=>$user->id,'exception'=>$exception::class]);}}
 return response()->json($user->profilePayload());
 }
 public function profilePhoto(Request $r,User $user){
  $viewer=$r->user();
  abort_unless($viewer->id===$user->id||$viewer->role==='admin',404);
  abort_unless($user->profile_photo_path&&Storage::disk('local')->exists($user->profile_photo_path),404);
  $mime=Storage::disk('local')->mimeType($user->profile_photo_path)?:'application/octet-stream';
  return Storage::disk('local')->response($user->profile_photo_path,'profile-'.$user->id,['Content-Type'=>$mime,'X-Content-Type-Options'=>'nosniff','Cache-Control'=>'private, no-store','Content-Security-Policy'=>"default-src 'none'; sandbox"],'inline');
 }
 public function saveProfilePhoto(Request $r,User $user){
  $viewer=$r->user();
  abort_unless($viewer->id===$user->id||$viewer->role==='admin',403);
  $data=$r->validate(['photo'=>'required|image|mimes:jpg,jpeg,png,webp|max:5120'],['photo.required'=>'Selecciona una imagen.','photo.image'=>'El archivo debe ser una imagen.','photo.mimes'=>'Usa una imagen JPG, PNG o WEBP.','photo.max'=>'La foto debe pesar como máximo 5 MB.']);
  $oldPath=$user->profile_photo_path;
  $newPath=$data['photo']->store('profile-photos/'.$user->id,'local');
  $user->profile_photo_path=$newPath;
  $user->save();
  if($oldPath)Storage::disk('local')->delete($oldPath);
  AuditLogger::record($r,'account.profile_photo.updated','Foto de perfil actualizada','user',(int)$user->id,$user->name);
  return response()->json($user->profilePayload());
 }
 public function deleteProfilePhoto(Request $r,User $user){
  $viewer=$r->user();
  abort_unless($viewer->id===$user->id||$viewer->role==='admin',403);
  $path=$user->profile_photo_path;
  $user->profile_photo_path=null;
  $user->save();
  if($path)Storage::disk('local')->delete($path);
  AuditLogger::record($r,'account.profile_photo.deleted','Foto de perfil quitada','user',(int)$user->id,$user->name);
  return response()->noContent();
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
  AuditLogger::record($r,'admin.notification_contact.'.(isset($contact)?'updated':'created'),isset($contact)?'Destinatario actualizado':'Destinatario agregado','notification_contact',(int)$id,$data['name'],Spaces::id(),['fields'=>['name','channel']]);
  return response()->json(DB::table('notification_contacts')->where('id',$id)->first());
 }
 public function deleteNotificationContact(Request $r,int $id){
  $this->admin($r);$contact=DB::table('notification_contacts')->where('id',$id)->where('workspace_id',Spaces::id())->first();abort_unless($contact,404);DB::table('notification_contacts')->where('id',$id)->delete();AuditLogger::record($r,'admin.notification_contact.deleted','Destinatario quitado','notification_contact',$id,$contact->name,Spaces::id());return response()->noContent();
 }
}
