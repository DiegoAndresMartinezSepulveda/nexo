<?php

namespace App\Http\Controllers;

use App\Models\Entry;
use App\Models\Media;
use App\Models\User;
use App\Support\Spaces;
use App\Support\WorkspaceContent as Content;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContentController extends Controller
{
    private const AUDIO_EXTENSIONS = ['mp3', 'm4a', 'aac', 'ogg', 'wav', 'flac', 'wma'];
    private const VIDEO_EXTENSIONS = ['mp4', 'mov', 'm4v', 'webm', 'avi', 'mkv', 'mpeg', 'mpg', '3gp'];
    private const ARCHIVE_EXTENSIONS = ['zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz'];
    private const APP_EXTENSIONS = ['apk', 'aab', 'xapk'];
    private const DOCUMENT_EXTENSIONS = ['txt', 'csv', 'md', 'rtf', 'odt', 'ods', 'odp', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'json', 'xml', 'yaml', 'yml', 'log', 'ini', 'conf'];

    private function whereExtension($query, array $extensions): void
    {
        $query->where(function ($files) use ($extensions) {
            foreach ($extensions as $extension) {
                $files->orWhereRaw('LOWER(name) LIKE ?', ['%.'.$extension]);
            }
        });
    }

    private function whereNotExtensions($query, array $extensions): void
    {
        foreach ($extensions as $extension) {
            $query->whereRaw('LOWER(name) NOT LIKE ?', ['%.'.$extension]);
        }
    }

    private function visibleEntries(Request $r)
    {
        $uid = $r->user()->id;

        return Entry::where('workspace_id', Spaces::id())->where(function ($q) use ($uid, $r) {
            $q->where('kind', '!=', 'diagram')->orWhere('visibility', 'workspace')->orWhere('user_id', $uid)
                ->orWhereHas('sharedUsers', fn ($share) => $share->where('users.id', $uid));
            if ($r->user()->role === 'admin') {
                $q->orWhere('kind', 'diagram');
            }
        });
    }

    private function canView(Request $r, Entry $entry): bool
    {
        return $entry->workspace_id === Spaces::id() && ($entry->kind !== 'diagram' || $entry->visibility === 'workspace' || $entry->user_id === $r->user()->id || $r->user()->role === 'admin' || $entry->sharedUsers()->where('users.id', $r->user()->id)->exists());
    }

    private function canEdit(Request $r, Entry $entry): bool
    {
        if ($r->user()->role === 'reader') {
            return false;
        }
        if ($entry->kind !== 'diagram') {
            return true;
        }

        return $entry->user_id === $r->user()->id || $r->user()->role === 'admin' || $entry->sharedUsers()->where('users.id', $r->user()->id)->wherePivot('permission', 'edit')->exists();
    }

    private function present(Request $r, Entry $entry): Entry
    {
        $entry->load('media');
        $entry->setAttribute('can_edit', $entry->kind !== 'diagram' ? $r->user()->role !== 'reader' : $this->canEdit($r, $entry));
        $entry->setAttribute('shares', $entry->kind === 'diagram' && ($entry->user_id === $r->user()->id || $r->user()->role === 'admin') ? $entry->sharedUsers()->get(['users.id', 'name', 'email'])->map(fn ($u) => ['user_id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'permission' => $u->pivot->permission])->values() : []);

        return $entry;
    }

    public function members(Request $r)
    {
        return response()->json(User::join('workspace_user', 'users.id', '=', 'workspace_user.user_id')->where('workspace_user.workspace_id', Spaces::id())->where('users.id', '!=', $r->user()->id)->orderBy('users.name')->get(['users.id', 'users.name', 'users.email']));
    }

    public function catalog(Request $r)
    {
        return response()->json(['clients' => DB::table('clients')->where('workspace_id', Spaces::id())->orderBy('name')->get(), 'projects' => DB::table('projects')->where('workspace_id', Spaces::id())->orderBy('name')->get()]);
    }

    public function saveCatalog(Request $r, string $kind, ?int $id = null)
    {
        abort_unless(in_array($kind, ['clients', 'projects']), 404);
        $uid = $r->user()->id;
        if ($id) {
            abort_unless(DB::table($kind)->where('id', $id)->where('workspace_id', Spaces::id())->exists(), 404);
        }
        $rules = ['name' => 'required|string|max:100'];
        if ($kind === 'clients') {
            $rules['name'] = ['required', 'string', 'max:100', Rule::unique('clients')->where('workspace_id', Spaces::id())->ignore($id)];
        } else {
            $rules += ['description' => 'nullable|string|max:5000', 'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')->where('workspace_id', Spaces::id())]];
        }
        if ($kind === 'clients') {
            $rules['code'] = 'nullable|string|max:40';
        }
        $data = $r->validate($rules);
        DB::transaction(function () use ($uid, $kind, &$id, $data) {
            // Changing a project's client also updates its linked records consistently.
            if ($id) {
                DB::table($kind)->where('id', $id)->update($data + ['updated_at' => now()]);
                if ($kind === 'projects') {
                    foreach (['tasks', 'entries'] as $table) {
                        DB::table($table)->where('workspace_id', Spaces::id())->where('project_id', $id)->update(['client_id' => $data['client_id'] ?? null]);
                    }
                }
            } else {
                $id = DB::table($kind)->insertGetId($data + ['workspace_id' => Spaces::id(), 'user_id' => $uid, 'created_at' => now(), 'updated_at' => now()]);
            }
            Content::log($uid, 'Guardado', $kind, $id, $data['name']);
        });

        return response()->json(DB::table($kind)->find($id));
    }

    public function deleteCatalog(Request $r, string $kind, int $id)
    {
        abort_unless(in_array($kind, ['clients', 'projects']), 404);
        $item = DB::table($kind)->where('id', $id)->where('workspace_id', Spaces::id())->first();
        abort_unless($item, 404);
        DB::transaction(function () use ($r, $kind, $id, $item) {
            DB::table($kind)->where('id', $id)->delete();
            Content::log($r->user()->id, 'Eliminado', $kind, $id, $item->name);
        });

        return response()->noContent();
    }

    public function index(Request $r)
    {
        $f = $r->validate(['kind' => ['required', Rule::in(['note', 'library', 'diagram'])], 'q' => 'nullable|string|max:200', 'archived' => 'nullable|boolean', 'client_id' => 'nullable|integer', 'project_id' => 'nullable|integer', 'category' => 'nullable|string|max:100', 'tag' => 'nullable|string|max:50', 'type' => ['nullable', Rule::in(['image', 'pdf', 'document', 'sql', 'archive', 'audio', 'video', 'apk', 'other'])]]);
        $query = $this->visibleEntries($r)->where('kind', $f['kind'])->where('archived', (bool) ($f['archived'] ?? false));
        foreach (['client_id', 'project_id', 'category'] as $field) {
            if (! empty($f[$field])) {
                $query->where($field, $f[$field]);
            }
        }
        if (! empty($f['tag'])) {
            $query->where(function ($q) use ($f) {
                $q->whereJsonContains('tags', $f['tag']);
                // Older MariaDB compares escaped and literal Unicode JSON strings differently.
                if (DB::connection()->getDriverName() === 'mysql') {
                    $q->orWhereRaw('JSON_CONTAINS(tags, ?)', [json_encode($f['tag'])]);
                }
            });
        }
        if (! empty($f['q'])) {
            $query->where(fn ($q) => $q->where('title', 'like', '%'.$f['q'].'%')->orWhere('search_text', 'like', '%'.$f['q'].'%'));
        }
        if (! empty($f['type'])) {
            $query->whereHas('media', function ($q) use ($f) {
                match ($f['type']) {
                    'image' => $q->whereIn('mime', ['image/png', 'image/jpeg', 'image/webp', 'image/gif']),
                    'video' => $q->where(fn ($file) => $file->where('mime', 'like', 'video/%')->orWhere(fn ($names) => $this->whereExtension($names, self::VIDEO_EXTENSIONS))),
                    'audio' => $q->where(fn ($file) => $file->where('mime', 'like', 'audio/%')->orWhere(fn ($names) => $this->whereExtension($names, self::AUDIO_EXTENSIONS))),
                    'pdf' => $q->where(fn ($file) => $file->where('mime', 'application/pdf')->orWhereRaw('LOWER(name) LIKE ?', ['%.pdf'])),
                    'apk' => $q->where(fn ($file) => $file->where('mime', 'application/vnd.android.package-archive')->orWhere(fn ($names) => $this->whereExtension($names, self::APP_EXTENSIONS))),
                    'sql' => $q->whereRaw('LOWER(name) LIKE ?', ['%.sql']),
                    'archive' => $q->where(fn ($file) => $this->whereExtension($file, self::ARCHIVE_EXTENSIONS)),
                    'document' => $q->where(fn ($file) => $this->whereExtension($file, self::DOCUMENT_EXTENSIONS)),
                    'other' => $q->where(function ($file) {
                        $file->where('mime', 'not like', 'image/%')->where('mime', 'not like', 'video/%')->where('mime', 'not like', 'audio/%')->where('mime', '!=', 'application/pdf')->where('mime', '!=', 'application/vnd.android.package-archive');
                        $this->whereNotExtensions($file, array_merge(self::AUDIO_EXTENSIONS, self::VIDEO_EXTENSIONS, self::ARCHIVE_EXTENSIONS, self::APP_EXTENSIONS, self::DOCUMENT_EXTENSIONS, ['pdf', 'sql']));
                    }),
                };
            });
        }

        return response()->json($query->with('media')->orderByDesc('pinned')->latest('updated_at')->get()->map(fn ($entry) => $this->present($r, $entry)));
    }

    public function show(Request $r, Entry $entry)
    {
        abort_unless($this->canView($r, $entry), 404);

        return response()->json($this->present($r, $entry));
    }

    public function save(Request $r, ?Entry $entry = null)
    {
        $entry ??= new Entry;
        if ($entry->exists) {
            abort_unless($this->canView($r, $entry), 404);
            abort_unless($this->canEdit($r, $entry), 403);
        }
        $data = $r->validate(Content::rules($r->user()->id) + [
            'kind' => ['required', Rule::in(['note', 'library', 'diagram'])], 'title' => 'required|string|max:180',
            'color' => ['required', Rule::in(['yellow', 'blue', 'green', 'red', 'purple', 'gray'])], 'pinned' => 'required|boolean', 'archived' => 'required|boolean',
            'category' => 'nullable|string|max:100', 'tags' => 'nullable|array|max:20', 'tags.*' => 'string|max:50',
            'diagram' => 'nullable|array:direction,nodes,edges,strokes', 'diagram.direction' => ['required_if:kind,diagram', Rule::in(['TD', 'LR'])],
            'diagram.nodes' => 'required_if:kind,diagram|array|min:1|max:60', 'diagram.nodes.*.id' => 'required|string|regex:/^n[0-9]+$/|distinct', 'diagram.nodes.*.x' => 'sometimes|numeric|min:0|max:5000', 'diagram.nodes.*.y' => 'sometimes|numeric|min:0|max:5000', 'diagram.nodes.*.label' => 'required|string|max:180', 'diagram.nodes.*.shape' => ['required', Rule::in(['process', 'decision', 'terminal', 'text', 'note', 'image', 'ellipse'])],
            'diagram.nodes.*.fill' => 'sometimes|string|regex:/^#[0-9a-fA-F]{6}$/', 'diagram.nodes.*.stroke' => 'sometimes|string|regex:/^#[0-9a-fA-F]{6}$/', 'diagram.nodes.*.bold' => 'sometimes|boolean', 'diagram.nodes.*.fontSize' => 'sometimes|integer|min:10|max:32', 'diagram.nodes.*.media_id' => 'sometimes|integer', 'diagram.nodes.*.listStyle' => ['sometimes', Rule::in(['bullet', 'number'])],
            'diagram.edges' => 'present_if:kind,diagram|array|max:120', 'diagram.edges.*.from' => 'required|string', 'diagram.edges.*.to' => 'required|string', 'diagram.edges.*.label' => 'nullable|string|max:80',
            'diagram.strokes' => 'nullable|array|max:100', 'diagram.strokes.*.points' => 'required|string|max:30000', 'diagram.strokes.*.color' => 'required|string|regex:/^#[0-9a-fA-F]{6}$/',
            'visibility' => ['nullable', Rule::in(['private', 'workspace', 'shared'])], 'shares' => 'nullable|array|max:100', 'shares.*.user_id' => ['required', 'integer', Rule::exists('workspace_user', 'user_id')->where('workspace_id', Spaces::id())], 'shares.*.permission' => ['required', Rule::in(['view', 'edit'])],
            'attachment_ids' => 'nullable|array|max:80', 'attachment_ids.*' => 'integer',
        ]);
        if ($entry->exists) {
            abort_unless($entry->kind === $data['kind'], 422);
        }
        if ($data['kind'] === 'diagram') {
            $nodes = array_column($data['diagram']['nodes'], 'id');
            foreach ($data['diagram']['edges'] as $edge) {
                if (! in_array($edge['from'], $nodes) || ! in_array($edge['to'], $nodes)) {
                    throw ValidationException::withMessages(['diagram' => 'Cada conexión debe apuntar a pasos existentes.']);
                }
            }
        }
        Content::validateProject($data);
        $data['blocks'] = Content::blocks($data['blocks'] ?? []);
        $ids = collect($data['attachment_ids'] ?? [])->merge(collect($data['diagram']['nodes'] ?? [])->pluck('media_id')->filter())->unique()->values()->all();
        unset($data['attachment_ids']);
        $shares = $data['shares'] ?? [];
        unset($data['shares']);
        if ($data['kind'] !== 'diagram') {
            $data['visibility'] = 'workspace';
        } else {
            $data['visibility'] ??= 'private';
        }
        $data['search_text'] = collect($data['blocks'])->where('type', 'text')->pluck('text')->implode("\n");
        $data['tags'] = array_values(array_unique($data['tags'] ?? []));
        DB::transaction(function () use ($r, $entry, $data, $ids, $shares) {
            $new = ! $entry->exists;
            $entry->fill($data);
            $entry->user_id ??= $r->user()->id;
            $entry->workspace_id = Spaces::id();
            $entry->save();
            Content::bind($r->user()->id, 'entry_id', $entry->id, $data['blocks'], $ids);
            if ($entry->kind === 'diagram' && ($entry->user_id === $r->user()->id || $r->user()->role === 'admin')) {
                $entry->sharedUsers()->sync(collect($shares)->mapWithKeys(fn ($share) => [(int) $share['user_id'] => ['permission' => $share['permission']]])->all());
            }
            Content::log($r->user()->id, $new ? 'Creado' : 'Actualizado', $entry->kind, $entry->id, $entry->title);
        });

        return response()->json($this->present($r, $entry), $entry->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $r, Entry $entry)
    {
        abort_unless($this->canView($r, $entry), 404);
        abort_unless($this->canEdit($r, $entry), 403);
        $paths = $entry->media()->pluck('path')->all();
        DB::transaction(function () use ($r, $entry) {
            Content::log($r->user()->id, 'Eliminado', $entry->kind, $entry->id, $entry->title);
            $entry->delete();
        });
        Storage::disk('local')->delete($paths);

        return response()->noContent();
    }

    public function upload(Request $r)
    {
        // Library assets can be any file type. Keep them private and downloadable;
        // media() only renders a small allowlist of safe preview formats inline.
        $r->validate(['file' => 'required|file|max:102400']);
        $file = $r->file('file');
        $path = $file->store('media', 'local');
        abort_unless($path, 500);
        try {
            $media = Media::create(['workspace_id' => Spaces::id(), 'user_id' => $r->user()->id, 'name' => mb_substr(basename($file->getClientOriginalName()), 0, 240), 'path' => $path, 'mime' => $file->getMimeType(), 'size' => $file->getSize()]);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        return response()->json($media, 201);
    }

    public function media(Request $r, Media $media)
    {
        abort_unless($media->workspace_id === Spaces::id(), 404);
        if ($r->boolean('preview') && in_array($media->mime, ['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'application/pdf'])) {
            return Storage::disk('local')->response($media->path, $media->name, ['Content-Type' => $media->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => "default-src 'none'; sandbox"], 'inline');
        }

        return Storage::disk('local')->download($media->path,$media->name,['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    public function history(Request $r)
    {
        $query = DB::table('activities')->where('workspace_id',Spaces::id());
        if ($r->filled('task_id')) {
            $query->where('subject_type','task')->where('subject_id',(int) $r->input('task_id'));
        }

        return response()->json($query->latest('id')->paginate(30));
    }
}
