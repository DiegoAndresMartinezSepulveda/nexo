<?php
namespace App\Http\Controllers;
use App\Models\{Entry, Media};
use App\Support\WorkspaceContent as Content;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\Rule;

class ContentController extends Controller {
    public function catalog(Request $r) {
        return response()->json(['clients'=>DB::table('clients')->where('workspace_id',\App\Support\Spaces::id())->orderBy('name')->get(), 'projects'=>DB::table('projects')->where('workspace_id',\App\Support\Spaces::id())->orderBy('name')->get()]);
    }
    public function saveCatalog(Request $r, string $kind, ?int $id = null) {
        abort_unless(in_array($kind,['clients','projects']),404);
        $uid=$r->user()->id;
        if ($id) abort_unless(DB::table($kind)->where('id',$id)->where('workspace_id',\App\Support\Spaces::id())->exists(),404);
        $rules=['name'=>'required|string|max:100'];
        if ($kind==='clients') $rules['name']=['required','string','max:100',Rule::unique('clients')->where('workspace_id',\App\Support\Spaces::id())->ignore($id)];
        else $rules += ['description'=>'nullable|string|max:5000','client_id'=>['nullable','integer',Rule::exists('clients','id')->where('workspace_id',\App\Support\Spaces::id())]];
        if ($kind==='clients') $rules['code']='nullable|string|max:40';
        $data=$r->validate($rules);
        DB::transaction(function () use ($uid,$kind,&$id,$data) {
            // Changing a project's client also updates its linked records consistently.
            if ($id) {
                DB::table($kind)->where('id',$id)->update($data+['updated_at'=>now()]);
                if ($kind==='projects') foreach (['tasks','entries'] as $table) DB::table($table)->where('workspace_id',\App\Support\Spaces::id())->where('project_id',$id)->update(['client_id'=>$data['client_id'] ?? null]);
            } else $id=DB::table($kind)->insertGetId($data+['workspace_id'=>\App\Support\Spaces::id(),'user_id'=>$uid,'created_at'=>now(),'updated_at'=>now()]);
            Content::log($uid,'Guardado',$kind,$id,$data['name']);
        });
        return response()->json(DB::table($kind)->find($id));
    }
    public function deleteCatalog(Request $r, string $kind, int $id) {
        abort_unless(in_array($kind,['clients','projects']),404);
        $item=DB::table($kind)->where('id',$id)->where('workspace_id',\App\Support\Spaces::id())->first(); abort_unless($item,404);
        DB::transaction(function () use ($r,$kind,$id,$item) { DB::table($kind)->where('id',$id)->delete(); Content::log($r->user()->id,'Eliminado',$kind,$id,$item->name); });
        return response()->noContent();
    }
    public function index(Request $r) {
        $f=$r->validate(['kind'=>['required',Rule::in(['note','library','diagram'])], 'q'=>'nullable|string|max:200','archived'=>'nullable|boolean', 'client_id'=>'nullable|integer','project_id'=>'nullable|integer','category'=>'nullable|string|max:100','tag'=>'nullable|string|max:50','type'=>['nullable',Rule::in(['image','pdf','document','sql','archive'])]]);
        $query=Entry::where('workspace_id',\App\Support\Spaces::id())->where('kind',$f['kind'])->where('archived',(bool)($f['archived'] ?? false));
        foreach (['client_id','project_id','category'] as $field) if (!empty($f[$field])) $query->where($field,$f[$field]);
        if (!empty($f['tag'])) $query->where(function($q)use($f){
            $q->whereJsonContains('tags',$f['tag']);
            // Older MariaDB compares escaped and literal Unicode JSON strings differently.
            if(DB::connection()->getDriverName()==='mysql') $q->orWhereRaw('JSON_CONTAINS(tags, ?)',[json_encode($f['tag'])]);
        });
        if (!empty($f['q'])) $query->where(fn($q)=>$q->where('title','like','%'.$f['q'].'%')->orWhere('search_text','like','%'.$f['q'].'%'));
        if (!empty($f['type'])) $query->whereHas('media',function($q)use($f){
            match($f['type']) {
                'image'=>$q->where('mime','like','image/%'), 'pdf'=>$q->where('mime','application/pdf'),
                'sql'=>$q->where('name','like','%.sql'), 'archive'=>$q->where('name','like','%.zip'),
                'document'=>$q->where('mime','not like','image/%')->where('mime','!=','application/pdf')->where('name','not like','%.sql')->where('name','not like','%.zip'),
            };
        });
        return response()->json($query->with('media')->orderByDesc('pinned')->latest('updated_at')->get());
    }
    public function show(Request $r, Entry $entry) { abort_unless($entry->workspace_id===\App\Support\Spaces::id(),404); return response()->json($entry->load('media')); }
    public function save(Request $r, ?Entry $entry = null) {
        $entry ??= new Entry;
        if ($entry->exists) abort_unless($entry->workspace_id===\App\Support\Spaces::id(),404);
        $data=$r->validate(Content::rules($r->user()->id)+[
            'kind'=>['required',Rule::in(['note','library','diagram'])], 'title'=>'required|string|max:180',
            'color'=>['required',Rule::in(['yellow','blue','green','red','purple','gray'])], 'pinned'=>'required|boolean','archived'=>'required|boolean',
            'category'=>'nullable|string|max:100','tags'=>'nullable|array|max:20','tags.*'=>'string|max:50',
            'diagram'=>'nullable|array:direction,nodes,edges','diagram.direction'=>['required_if:kind,diagram',Rule::in(['TD','LR'])],
            'diagram.nodes'=>'required_if:kind,diagram|array|min:1|max:60','diagram.nodes.*.id'=>'required|string|regex:/^n[0-9]+$/|distinct','diagram.nodes.*.x'=>'sometimes|numeric|min:0|max:5000','diagram.nodes.*.y'=>'sometimes|numeric|min:0|max:5000','diagram.nodes.*.label'=>'required|string|max:180','diagram.nodes.*.shape'=>['required',Rule::in(['process','decision','terminal'])],
            'diagram.edges'=>'present_if:kind,diagram|array|max:120','diagram.edges.*.from'=>'required|string','diagram.edges.*.to'=>'required|string','diagram.edges.*.label'=>'nullable|string|max:80',
            'attachment_ids'=>'nullable|array|max:80','attachment_ids.*'=>'integer',
        ]);
        if ($entry->exists) abort_unless($entry->kind===$data['kind'],422);
        if ($data['kind']==='diagram') {
            $nodes=array_column($data['diagram']['nodes'],'id');
            foreach($data['diagram']['edges'] as $edge) if(!in_array($edge['from'],$nodes)||!in_array($edge['to'],$nodes)) throw \Illuminate\Validation\ValidationException::withMessages(['diagram'=>'Cada conexión debe apuntar a pasos existentes.']);
        }
        Content::validateProject($data); $data['blocks']=Content::blocks($data['blocks'] ?? []);
        $ids=$data['attachment_ids'] ?? []; unset($data['attachment_ids']);
        $data['search_text']=collect($data['blocks'])->where('type','text')->pluck('text')->implode("\n");
        $data['tags']=array_values(array_unique($data['tags'] ?? []));
        DB::transaction(function () use ($r,$entry,$data,$ids) {
            $new=!$entry->exists; $entry->fill($data); $entry->user_id ??= $r->user()->id; $entry->workspace_id=\App\Support\Spaces::id(); $entry->save();
            Content::bind($r->user()->id,'entry_id',$entry->id,$data['blocks'],$ids);
            Content::log($r->user()->id,$new?'Creado':'Actualizado',$entry->kind,$entry->id,$entry->title);
        });
        return response()->json($entry->load('media'),$entry->wasRecentlyCreated?201:200);
    }
    public function destroy(Request $r, Entry $entry) {
        abort_unless($entry->workspace_id===\App\Support\Spaces::id(),404); $paths=$entry->media()->pluck('path')->all();
        DB::transaction(function () use ($r,$entry) { Content::log($r->user()->id,'Eliminado',$entry->kind,$entry->id,$entry->title); $entry->delete(); });
        Storage::disk('local')->delete($paths); return response()->noContent();
    }
    public function upload(Request $r) {
        $r->validate(['file'=>'required|file|max:10240|extensions:jpg,jpeg,png,webp,gif,pdf,txt,csv,doc,docx,xls,xlsx,ppt,pptx,zip,sql|mimes:jpg,jpeg,png,webp,gif,pdf,txt,csv,doc,docx,xls,xlsx,ppt,pptx,zip,sql']);
        $file=$r->file('file'); $path=$file->store('media','local'); abort_unless($path,500);
        try {$media=Media::create(['workspace_id'=>\App\Support\Spaces::id(),'user_id'=>$r->user()->id,'name'=>mb_substr(basename($file->getClientOriginalName()),0,240),'path'=>$path,'mime'=>$file->getMimeType(),'size'=>$file->getSize()]);}
        catch(\Throwable $e){Storage::disk('local')->delete($path);throw $e;}
        return response()->json($media,201);
    }
    public function media(Request $r, Media $media) {
        abort_unless($media->workspace_id===\App\Support\Spaces::id(),404);
        if ($r->boolean('preview') && in_array($media->mime,['image/png','image/jpeg','image/webp','image/gif','application/pdf'])) {
            return Storage::disk('local')->response($media->path,$media->name,['Content-Type'=>$media->mime,'X-Content-Type-Options'=>'nosniff','Cache-Control'=>'private, no-store','Content-Security-Policy'=>"default-src 'none'; sandbox"],'inline');
        }
        return Storage::disk('local')->download($media->path,$media->name,['X-Content-Type-Options'=>'nosniff','Cache-Control'=>'private, no-store']);
    }
    public function history(Request $r) {
        $query=DB::table('activities')->where('workspace_id',\App\Support\Spaces::id());
        if ($r->filled('task_id')) $query->where('subject_type','task')->where('subject_id',(int)$r->input('task_id'));
        return response()->json($query->latest('id')->paginate(30));
    }
}
