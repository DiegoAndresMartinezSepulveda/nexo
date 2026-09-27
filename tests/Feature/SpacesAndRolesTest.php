<?php
namespace Tests\Feature;
use App\Models\{User,Task,Media};
use App\Support\Spaces;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class SpacesAndRolesTest extends TestCase {
 use RefreshDatabase;
 private function note():array{return ['kind'=>'note','title'=>'Ñandú · revisión áéíóú ¿sí?','color'=>'blue','pinned'=>false,'archived'=>false,'blocks'=>[['type'=>'text','html'=>'<p>Contraseña y certificación</p>']]];}
 public function test_spaces_isolate_every_resource_including_direct_downloads():void{
  $u=User::factory()->create(['role'=>'admin']);$this->actingAs($u);
  $a=DB::table('workspace_user')->where('user_id',$u->id)->value('workspace_id');$b=Spaces::create($u,'Personal');
  $this->withHeader('X-Workspace-ID',(string)$a);
  $client=$this->postJson('/api/catalog/clients',['name'=>'FEN','code'=>'9966'])->assertOk()->assertJsonPath('code','9966')->json('id');
  $entry=$this->postJson('/api/entries',$this->note()+['client_id'=>$client])->assertCreated()->json('id');
  $task=$this->postJson('/api/tasks',['title'=>'Trabajo Safin','environment'=>'backlog','status'=>'pending','priority'=>'normal'])->assertCreated()->json('id');
  $this->withHeader('X-Workspace-ID',(string)$b);
  $this->getJson('/api/tasks')->assertJsonCount(0,'tasks');$this->getJson('/api/entries?kind=note')->assertJsonCount(0);$this->getJson('/api/catalog')->assertJsonCount(0,'clients');$this->getJson('/api/history')->assertJsonCount(0,'data');
  $this->getJson('/api/tasks/'.$task)->assertNotFound();$this->getJson('/api/entries/'.$entry)->assertNotFound();
  $this->postJson('/api/entries',$this->note()+['client_id'=>$client])->assertUnprocessable();
  $this->postJson('/api/catalog/clients',['name'=>'FEN','code'=>'otro'])->assertOk();
  $this->withHeader('X-Workspace-ID',(string)$a);$this->getJson('/api/entries/'.$entry)->assertOk()->assertJsonPath('title','Ñandú · revisión áéíóú ¿sí?');
  $this->actingAs(User::factory()->create());$this->getJson('/api/tasks')->assertForbidden();
 }
 public function test_browser_asset_urls_validate_membership_and_selected_space():void{
  \Illuminate\Support\Facades\Storage::fake('local');$u=User::factory()->create();$this->actingAs($u);$b=Spaces::create($u,'Personal');
  $this->withHeader('X-Workspace-ID',(string)$b);
  $id=$this->post('/api/media',['file'=>\Illuminate\Http\UploadedFile::fake()->createWithContent('referencia.txt','Árbol')],['Accept'=>'application/json'])->assertCreated()->json('id');
  $this->flushHeaders();$this->get('/api/media/'.$id.'?workspace='.$b)->assertDownload('referencia.txt');
  $this->getJson('/api/media/'.$id)->assertNotFound();
  $this->actingAs(User::factory()->create());$this->getJson('/api/media/'.$id.'?workspace='.$b)->assertForbidden();
 }
 public function test_admin_can_assign_editor_and_reader_without_granting_admin():void{
  $admin=User::factory()->create(['role'=>'admin']);$this->actingAs($admin);$space=DB::table('workspace_user')->where('user_id',$admin->id)->value('workspace_id');
  $data=['name'=>'Colaborador','email'=>'editor@example.test','password'=>'TestOnlyPassword123','role'=>'editor','workspace_ids'=>[$space]];
  $id=$this->postJson('/api/users',$data)->assertOk()->assertJsonMissingPath('password')->json('id');
  $this->postJson('/api/users',array_replace($data,['email'=>'admin@example.test','role'=>'admin']))->assertUnprocessable();
  $this->actingAs(User::find($id));$this->postJson('/api/entries',$this->note())->assertCreated();
  $this->getJson('/api/users')->assertForbidden();$this->postJson('/api/users',$data)->assertForbidden();$this->postJson('/api/workspaces',['name'=>'No','color'=>'blue'])->assertForbidden();
  $mobileToken=User::find($id)->createToken('member-phone');
  $this->actingAs($admin);$this->putJson('/api/users/'.$id,array_replace($data,['role'=>'reader','password'=>null]))->assertOk();
  $this->assertSame(2,(int)User::find($id)->auth_version);$this->assertDatabaseMissing('personal_access_tokens',['id'=>$mobileToken->accessToken->id]);
  $this->actingAs(User::find($id));
  $this->getJson('/api/entries?kind=note')->assertOk()->assertJsonCount(1);$this->postJson('/api/entries',$this->note())->assertForbidden();$this->postJson('/api/logout')->assertNoContent();
 }
 public function test_diagrams_validate_edges_and_persist_coordinates():void{
  $this->actingAs(User::factory()->create());
  $d=['direction'=>'TD','nodes'=>[['id'=>'n1','label'=>'Inicio áéíóú','shape'=>'terminal','x'=>40,'y'=>20],['id'=>'n2','label'=>'Validar','shape'=>'decision']],'edges'=>[['from'=>'n1','to'=>'n2','label'=>'Solicitud']]];
  $payload=array_replace($this->note(),['kind'=>'diagram','diagram'=>$d]);
  $id=$this->postJson('/api/entries',$payload)->assertCreated()->assertJsonPath('diagram.nodes.0.x',40)->json('id');
  $this->getJson('/api/entries?kind=diagram')->assertJsonCount(1);$this->getJson('/api/entries/'.$id)->assertJsonPath('diagram.edges.0.to','n2');
  $payload['diagram']=$d;$payload['diagram']['nodes'][0]['x']=10000;$payload['diagram']['nodes'][0]['y']=7000;$this->putJson('/api/entries/'.$id,$payload)->assertOk()->assertJsonPath('diagram.nodes.0.x',10000)->assertJsonPath('diagram.nodes.0.y',7000);
  $payload['diagram']['edges'][0]['to']='missing';$this->putJson('/api/entries/'.$id,$payload)->assertUnprocessable();
  $payload['diagram']=$d;$payload['diagram']['nodes'][0]['x']=-1;$this->putJson('/api/entries/'.$id,$payload)->assertUnprocessable();
  $payload['diagram']=$d;$payload['diagram']['nodes'][1]['id']='n1';$this->putJson('/api/entries/'.$id,$payload)->assertUnprocessable();
 }
}
