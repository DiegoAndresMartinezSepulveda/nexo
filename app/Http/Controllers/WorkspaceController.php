<?php

namespace App\Http\Controllers;

use App\Models\{Task, Attachment};

use Illuminate\Http\Request;

use Illuminate\Support\Facades\{Auth, DB, Storage};

use Illuminate\Validation\Rule;

use App\Support\WorkspaceContent as Content;

use App\Models\Media;

class WorkspaceController extends Controller {

    public function login(Request $r) {

        $credentials = $r->validate(['email' => 'required|email', 'password' => 'required|string']);

        if (!Auth::attempt($credentials)) throw \Illuminate\Validation\ValidationException::withMessages(['email' => 'El correo o la contraseña no coinciden.']);

        $r->session()->regenerate();

        return response()->json(['user' => $r->user()->only('id', 'name', 'email', 'role')]);

    }

    public function logout(Request $r) {

        Auth::logout(); $r->session()->invalidate(); $r->session()->regenerateToken();

        return response()->noContent();

    }

    public function index(Request $r) {

        $f = $r->validate(['q' => 'nullable|string|max:200', 'environment' => ['nullable', Rule::in(array_keys(Task::ENVIRONMENTS))]]);

        $base = Task::where('workspace_id', \App\Support\Spaces::id());

        $counts = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $tasks = $base->when($f['q'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('title', 'like', "%{$s}%")->orWhere('description', 'like', "%{$s}%")))

            ->when($f['environment'] ?? null, fn ($q, $e) => $q->where('environment', $e))->withCount('attachments')->latest('updated_at')->get();

        return response()->json(compact('tasks', 'counts'));

    }

    private function authorizeTask(Request $r, Task $task): void { abort_unless($task->workspace_id === \App\Support\Spaces::id(), 404); }

    public function edit(Request $r, Task $task) { $this->authorizeTask($r, $task); return response()->json($task->load('attachments')); }

    private function save(Request $r, Task $task) {

        $data = $r->validate(Content::rules($r->user()->id, 'notes_blocks') + [

            'is_fire'=>'sometimes|boolean', 'sql_notes' => 'nullable|string|max:50000', 'title' => 'required|string|max:180', 'description' => 'nullable|string|max:20000',

            'status' => ['required', Rule::in(array_keys(Task::STATUSES))],

            'environment' => ['required', Rule::in(array_keys(Task::ENVIRONMENTS))],

            'priority' => ['required', Rule::in(array_keys(Task::PRIORITIES))],

            'due_date' => 'nullable|date_format:Y-m-d', 'checklist_text' => 'nullable|string|max:10000',

            'files' => 'nullable|array|max:8', 'files.*' => 'file|max:10240|mimes:jpg,jpeg,png,webp,gif,pdf,txt,csv,doc,docx,xls,xlsx,ppt,pptx,zip',

        ], ['title.required' => 'Escribe un título para la tarea.', 'files.*.max' => 'Cada archivo debe pesar como máximo 10 MB.', 'files.*.mimes' => 'Adjunta imágenes, PDF, documentos, texto o ZIP.']);

        Content::validateProject(array_replace($task->only(['client_id','project_id']),$data));

        $this->validateState($data['environment'],$data['status']);

        if (array_key_exists('notes_blocks', $data)) $data['notes_blocks'] = Content::blocks($data['notes_blocks']);

        $previous = collect($task->checklist ?? [])->keyBy('text');

        $data['checklist'] = collect(preg_split('/\R/u', $data['checklist_text'] ?? ''))->map(fn ($s) => trim($s))->filter()->unique()->values()

            ->map(fn ($s) => ['text' => $s, 'done' => (bool) ($previous->get($s)['done'] ?? false)])->all();

        unset($data['checklist_text'], $data['files']); $paths = [];

        try {

            DB::transaction(function () use ($r, $task, $data, &$paths) {

                $new = !$task->exists;

                $task->fill($data); $task->user_id ??= $r->user()->id; $task->workspace_id=\App\Support\Spaces::id(); $task->save();

                if (array_key_exists('notes_blocks', $data)) Content::bind($r->user()->id, 'task_id', $task->id, $data['notes_blocks']);

                Content::log($r->user()->id, $new ? 'Creado' : 'Actualizado', 'task', $task->id, $task->title);

                foreach ($r->file('files', []) as $file) {

                    $path = $file->store('attachments', 'local');

                    if (!$path) throw new \RuntimeException('No se pudo guardar el archivo.');

                    $paths[] = $path;

                    $task->attachments()->create(['name' => mb_substr(basename($file->getClientOriginalName()), 0, 240), 'path' => $path, 'size' => $file->getSize()]);

                }

            });

        } catch (\Throwable $e) { Storage::disk('local')->delete($paths); throw $e; }

        return response()->json($task->load('attachments'), $task->wasRecentlyCreated ? 201 : 200);

    }

    public function store(Request $r) { return $this->save($r, new Task); }

    public function update(Request $r, Task $task) { $this->authorizeTask($r, $task); return $this->save($r, $task); }

    public function status(Request $r, Task $task) {

        $this->authorizeTask($r, $task); $data=$r->validate(['status' => ['required', Rule::in(array_keys(Task::STATUSES))]]);

        $this->validateState($task->environment,$data['status']); $task->update($data);

        Content::log($r->user()->id, 'Estado: '.Task::STATUSES[$task->status], 'task', $task->id, $task->title);

        return response()->json($task);

    }

    private function validateState(string $environment, string $status): void {

        if (!in_array($status,Task::allowedStatuses($environment))) throw \Illuminate\Validation\ValidationException::withMessages(['status'=>'Ese estado no corresponde al ambiente seleccionado. Certificación usa revisión; QA y Producción usan revisión o completada.']);

    }

    public function move(Request $r, Task $task) {

        $this->authorizeTask($r,$task);

        $data=$r->validate(['status'=>['required',Rule::in(array_keys(Task::STATUSES))],'environment'=>['required',Rule::in(array_keys(Task::ENVIRONMENTS))]]);

        $this->validateState($data['environment'],$data['status']);

        DB::transaction(function()use($r,$task,$data){ $task->update($data); Content::log($r->user()->id,'Movida a '.Task::ENVIRONMENTS[$task->environment].' · '.Task::STATUSES[$task->status],'task',$task->id,$task->title); });

        return response()->json($task);

    }

    public function checklist(Request $r, Task $task) {

        $this->authorizeTask($r, $task); $data = $r->validate(['index' => 'required|integer|min:0', 'done' => 'required|boolean']);

        DB::transaction(function () use ($task, $data) {

            $locked = Task::whereKey($task->id)->lockForUpdate()->firstOrFail(); $items = $locked->checklist ?? [];

            abort_unless(isset($items[$data['index']]), 404);

            $items[$data['index']]['done'] = (bool) $data['done']; $locked->update(['checklist' => $items]);

        });

        Content::log($r->user()->id,'Lista de entrega actualizada','task',$task->id,$task->title);
        return response()->json($task->fresh()->load('attachments'));

    }

    public function download(Request $r, Attachment $attachment) {

        $this->authorizeTask($r, $attachment->task);

        return Storage::disk('local')->download($attachment->path, $attachment->name, ['X-Content-Type-Options' => 'nosniff']);

    }

    public function deleteAttachment(Request $r, Attachment $attachment) {

        $this->authorizeTask($r, $attachment->task); Content::log($r->user()->id,'Adjunto eliminado','task',$attachment->task_id,$attachment->task->title); Storage::disk('local')->delete($attachment->path); $attachment->delete();

        return response()->noContent();

    }

    public function destroy(Request $r, Task $task) {

        $this->authorizeTask($r, $task);

        foreach ($task->attachments as $file) Storage::disk('local')->delete($file->path);

        foreach (Media::where('task_id',$task->id)->get() as $media) Storage::disk('local')->delete($media->path);

        Content::log($r->user()->id, 'Eliminado', 'task', $task->id, $task->title);

        $task->delete(); return response()->noContent();

    }

}
