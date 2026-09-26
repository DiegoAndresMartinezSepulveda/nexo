<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Media;
use App\Models\Task;
use App\Support\Spaces;
use App\Support\LoginProtection;
use App\Support\WorkspaceContent as Content;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorkspaceController extends Controller
{
    public function login(Request $r, LoginProtection $protection)
    {

        if ($blocked = $protection->blockedResponse($r)) {
            return $blocked;
        }

        $credentials = $r->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'captcha_id' => 'nullable|string|max:64',
            'captcha_answer' => 'nullable|string|max:20',
        ]);

        if (! $protection->verifyCaptchaIfRequired($r)) {
            return $protection->failed($r, 'captcha_answer', 'La verificación no es correcta o venció.');
        }

        if (! Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password']])) {
            return $protection->failed($r, 'email', 'El correo o la contraseña no coinciden.');
        }

        $protection->succeeded($r);

        $r->session()->regenerate();

        return response()->json(['user' => $r->user()->profilePayload()])
            ->header('Cache-Control', 'no-store, private');

    }

    public function logout(Request $r)
    {

        $token = $r->user()?->currentAccessToken();
        if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
            $token->delete();
        }

        Auth::guard('web')->logout();
        Auth::forgetGuards();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return response()->noContent();

    }

    public function index(Request $r)
    {

        $f = $r->validate(['q' => 'nullable|string|max:200', 'environment' => ['nullable', Rule::in(array_keys(Task::ENVIRONMENTS))]]);

        $base = Task::where('workspace_id', Spaces::id());

        $counts = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $tasks = $base->when($f['q'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('title', 'like', "%{$s}%")->orWhere('description', 'like', "%{$s}%")))

            ->when($f['environment'] ?? null, fn ($q, $e) => $q->where('environment', $e))->withCount('attachments')->latest('updated_at')->get();

        return response()->json(compact('tasks', 'counts'));

    }

    private function authorizeTask(Request $r, Task $task): void
    {
        abort_unless($task->workspace_id === Spaces::id(), 404);
    }

    public function edit(Request $r, Task $task)
    {
        $this->authorizeTask($r, $task);

        return response()->json($task->load('attachments'));
    }

    private function save(Request $r, Task $task)
    {

        $data = $r->validate(Content::rules($r->user()->id, 'notes_blocks') + Content::rules($r->user()->id, 'description_blocks') + [

            'is_fire' => 'sometimes|boolean', 'task_type' => ['sometimes', Rule::in(['task', 'bug'])], 'sql_notes' => 'nullable|string|max:50000', 'title' => 'required|string|max:180', 'description' => 'nullable|string|max:20000',

            'status' => ['required', Rule::in(array_keys(Task::STATUSES))],

            'environment' => ['required', Rule::in(array_keys(Task::ENVIRONMENTS))],

            'priority' => ['required', Rule::in(array_keys(Task::PRIORITIES))],

            'due_date' => 'nullable|date_format:Y-m-d', 'estimated_delivery_at' => 'nullable|date', 'autosave' => 'sometimes|boolean', 'checklist_text' => 'nullable|string|max:10000',
            'notify_on_production' => 'sometimes|boolean', 'notify_emails' => 'nullable|string|max:2000', 'notification_message' => 'nullable|string|max:5000', 'email_notification_events_present' => 'sometimes|boolean', 'email_notification_events' => 'sometimes|array|max:7', 'email_notification_events.*' => ['string', Rule::in(array_merge(['production_completed'], array_keys(Task::ENVIRONMENTS)))], 'notify_message_on_production' => 'sometimes|boolean', 'notify_message_emails' => 'nullable|string|max:2000', 'notification_message_short' => 'nullable|string|max:2000', 'tags' => 'nullable|string|max:1000',
            'notify_include_client' => 'sometimes|boolean', 'notify_include_project' => 'sometimes|boolean', 'notify_include_title' => 'sometimes|boolean', 'notify_include_description' => 'sometimes|boolean', 'notify_include_code' => 'sometimes|boolean',
            'notify_include_status' => 'sometimes|boolean', 'notify_include_checklist' => 'sometimes|boolean', 'environment_return_resolved' => 'sometimes|boolean', 'environment_return_solution' => 'nullable|string|max:10000',

            'files' => 'nullable|array|max:8', 'files.*' => 'file|max:10240|mimes:jpg,jpeg,png,webp,gif,pdf,txt,csv,doc,docx,xls,xlsx,ppt,pptx,zip',

        ], ['title.required' => 'Escribe un título para la tarea.', 'files.*.max' => 'Cada archivo debe pesar como máximo 10 MB.', 'files.*.mimes' => 'Adjunta imágenes, PDF, documentos, texto o ZIP.']);

        $autosave = (bool) ($data['autosave'] ?? false);
        unset($data['autosave']);
        if (array_key_exists('email_notification_events_present', $data)) {
            $data['email_notification_events'] = array_values(array_unique($data['email_notification_events'] ?? []));
            unset($data['email_notification_events_present']);
        } else {
            $data['email_notification_events'] = $data['email_notification_events'] ?? $task->email_notification_events ?? ['production_completed'];
        }
        Content::validateProject(array_replace($task->only(['client_id', 'project_id']), $data));

        $this->validateState($data['environment'], $data['status'], $task);

        if (array_key_exists('notes_blocks', $data)) {
            $data['notes_blocks'] = Content::blocks($data['notes_blocks']);
        }
        if (array_key_exists('description_blocks', $data)) {
            $data['description_blocks'] = Content::blocks($data['description_blocks']);
            $data['description'] = mb_substr(collect($data['description_blocks'])->pluck('text')->filter()->join("\n"), 0, 20000);
        }

        $previous = collect($task->checklist ?? [])->keyBy('text');

        $data['checklist'] = collect(preg_split('/\R/u', $data['checklist_text'] ?? ''))->map(fn ($s) => trim($s))->filter()->unique()->values()

            ->map(fn ($s) => ['text' => $s, 'done' => (bool) ($previous->get($s)['done'] ?? false)])->all();

        $data['notify_emails'] = $this->normalizeEmails($data['notify_emails'] ?? '');
        $data['notify_message_emails'] = $this->normalizeEmails($data['notify_message_emails'] ?? '');
        $data['tags'] = collect(explode(',', $data['tags'] ?? ''))->map(fn ($tag) => mb_substr(trim($tag), 0, 40))->filter()->unique()->take(20)->values()->all();
        $data['notification_fields'] = collect(['client', 'project', 'title', 'description', 'code', 'status', 'checklist', 'attachments'])->filter(fn ($field) => (bool) ($data['notify_include_'.$field] ?? false))->values()->all();
        foreach (['client', 'project', 'title', 'description', 'code', 'status', 'checklist', 'attachments'] as $field) {
            unset($data['notify_include_'.$field]);
        }
        foreach (['notify_emails' => 'notify_emails', 'notify_message_emails' => 'notify_message_emails'] as $emails => $field) {
            foreach ($data[$emails] as $email) {
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw ValidationException::withMessages([$field => 'Revisa los correos de notificación.']);
                }
            }
        }
        if (empty($data['notify_on_production'])) {
            $data['notify_emails'] = [];
        }
        if (empty($data['notify_message_on_production'])) {
            $data['notify_message_emails'] = [];
        }
        unset($data['checklist_text'], $data['files']);
        $paths = [];

        try {

            $wasReturnResolved = (bool) $task->environment_return_resolved;

            DB::transaction(function () use ($r, $task, $data, &$paths, $wasReturnResolved) {

                $new = ! $task->exists;
                $environmentChanged = $new || $task->environment !== ($data['environment'] ?? $task->environment);

                $task->fill($data);
                if ($environmentChanged) {
                    $this->prepareEnvironmentEmailEntry($task);
                }
                $task->user_id ??= $r->user()->id;
                $task->workspace_id = Spaces::id();
                $task->save();

                if (array_key_exists('notes_blocks', $data) || array_key_exists('description_blocks', $data)) {
                    Content::bind($r->user()->id, 'task_id', $task->id, array_merge($data['description_blocks'] ?? $task->description_blocks ?? [], $data['notes_blocks'] ?? $task->notes_blocks ?? []));
                }

                Content::log($r->user()->id, $new ? 'Creado' : 'Actualizado', 'task', $task->id, $task->title);

                if (! $new && ! $wasReturnResolved && $task->environment_return_resolved) {
                    $action = 'Error del regreso marcado como resuelto';
                    if ($task->environment_return_solution) {
                        $action .= ' · Solución: '.mb_substr($task->environment_return_solution, 0, 180);
                    }
                    Content::log($r->user()->id, $action, 'task', $task->id, $task->title);
                }

                foreach ($r->file('files', []) as $file) {

                    $path = $file->store('attachments', 'local');

                    if (! $path) {
                        throw new \RuntimeException('No se pudo guardar el archivo.');
                    }

                    $paths[] = $path;

                    $task->attachments()->create(['name' => mb_substr(basename($file->getClientOriginalName()), 0, 240), 'path' => $path, 'size' => $file->getSize()]);

                }

            });

        } catch (\Throwable $e) {
            Storage::disk('local')->delete($paths);
            throw $e;
        }

        $this->resetProductionNotificationIfNeeded($task);
        $mail = $autosave ? 'not_requested' : $this->notifyTaskEmail($task);

        return response()->json($task->load('attachments')->setAttribute('notification', $mail), $task->wasRecentlyCreated ? 201 : 200);

    }

    public function store(Request $r)
    {
        return $this->save($r, new Task);
    }

    public function update(Request $r, Task $task)
    {
        $this->authorizeTask($r, $task);

        return $this->save($r, $task);
    }

    public function status(Request $r, Task $task)
    {

        $this->authorizeTask($r, $task);
        $data = $r->validate(['status' => ['required', Rule::in(array_keys(Task::STATUSES))]]);

        $this->validateState($task->environment, $data['status'], $task);
        $task->update($data);
        $this->resetProductionNotificationIfNeeded($task);
        $mail = $this->notifyTaskEmail($task);

        Content::log($r->user()->id, 'Estado: '.Task::STATUSES[$task->status], 'task', $task->id, $task->title);
        return response()->json($task->setAttribute('notification', $mail));

    }

    private function validateState(string $environment, string $status, ?Task $task = null): void
    {
        $legacyPending = $task && $task->status === 'pending' && $task->environment === $environment && $status === 'pending';
        if (! $legacyPending && ! in_array($status, Task::allowedStatuses($environment))) {
            throw ValidationException::withMessages(['status' => 'Ese estado no corresponde al ambiente seleccionado. Certificación usa revisión; QA y Producción usan revisión o completada.']);
        }

    }

    public function move(Request $r, Task $task)
    {

        $this->authorizeTask($r, $task);

        $data = $r->validate(['status' => ['required', Rule::in(array_keys(Task::STATUSES))], 'environment' => ['required', Rule::in(array_keys(Task::ENVIRONMENTS))], 'rollback_reason' => 'nullable|string|max:5000']);

        $returningToEarlierEnvironment = $this->isEnvironmentReturn($task->environment, $data['environment']);
        $reason = trim((string) ($data['rollback_reason'] ?? ''));
        if ($returningToEarlierEnvironment && $reason === '') {
            throw ValidationException::withMessages(['rollback_reason' => 'Explica qué falló antes de devolver la tarea a un ambiente anterior.']);
        }
        unset($data['rollback_reason']);

        $this->validateState($data['environment'], $data['status'], $task);

        DB::transaction(function () use ($r, $task, $data, $returningToEarlierEnvironment, $reason) {
            $environmentChanged = $task->environment !== $data['environment'];
            if ($returningToEarlierEnvironment) {
                $returnedAt = now();
                $data['environment_return_from'] = $task->environment;
                $data['environment_return_to'] = $data['environment'];
                $data['environment_return_reason'] = $reason;
                $data['environment_return_solution'] = null;
                $data['environment_return_resolved'] = false;
                $data['environment_returned_at'] = $returnedAt;
                // Keep the old fields populated for installations that still use the previous UI.
                if ($task->environment === 'production' && $data['environment'] === 'development') {
                    $data['production_return_reason'] = $reason;
                    $data['production_returned_at'] = $returnedAt;
                }
            }
            if ($environmentChanged) {
                $data['environment_entered_at'] = now();
                $data['environment_notification_notified_at'] = $this->environmentEmailIsSelected($task, $data['environment']) ? null : $data['environment_entered_at'];
            }
            $task->update($data);
            $action = 'Movida a '.Task::ENVIRONMENTS[$task->environment].' · '.Task::STATUSES[$task->status];
            if ($returningToEarlierEnvironment) {
                $action .= ' · Error registrado: '.mb_substr($reason, 0, 180);
            }
            Content::log($r->user()->id, $action, 'task', $task->id, $task->title);
        });

        $this->resetProductionNotificationIfNeeded($task);
        $mail = $this->notifyTaskEmail($task);
        return response()->json($task->setAttribute('notification', $mail));

    }

    private function isEnvironmentReturn(string $from, string $to): bool
    {
        $order = ['backlog', 'local', 'development', 'qa', 'certification', 'production'];
        $fromPosition = array_search($from, $order, true);
        $toPosition = array_search($to, $order, true);

        return $fromPosition !== false && $toPosition !== false && $fromPosition > $toPosition;
    }

    private function emailNotificationEvents(Task $task): array
    {
        return $task->email_notification_events ?? ['production_completed'];
    }

    private function environmentEmailIsSelected(Task $task, string $environment): bool
    {
        return (bool) $task->notify_on_production
            && count($task->notify_emails ?? []) > 0
            && in_array($environment, $this->emailNotificationEvents($task), true);
    }

    private function prepareEnvironmentEmailEntry(Task $task): void
    {
        $enteredAt = now();
        $task->environment_entered_at = $enteredAt;
        $task->environment_notification_notified_at = $this->environmentEmailIsSelected($task, $task->environment)
            ? null
            : $enteredAt;
    }

    private function notifyTaskEmail(Task $task): string
    {
        if (! $task->notify_on_production || ! count($task->notify_emails ?? [])) {
            return 'not_requested';
        }

        $events = $this->emailNotificationEvents($task);
        $completionEligible = in_array('production_completed', $events, true)
            && $task->environment === 'production'
            && $task->status === 'done'
            && ! $task->production_notified_at;
        $entryAt = $task->environment_entered_at;
        $entryPending = $entryAt
            && in_array($task->environment, $events, true)
            && (! $task->environment_notification_notified_at || $task->environment_notification_notified_at->lt($entryAt));

        $claimedAt = null;
        $claimedCompletion = false;
        if ($completionEligible) {
            $claimedAt = now();
            $claimedCompletion = (bool) Task::whereKey($task->id)->whereNull('production_notified_at')->update(['production_notified_at' => $claimedAt]);
        }

        $claimedEntry = false;
        if ($entryPending) {
            $claimedEntry = (bool) Task::whereKey($task->id)
                ->where('environment', $task->environment)
                ->where('environment_entered_at', $entryAt)
                ->where(function ($query) {
                    $query->whereNull('environment_notification_notified_at')
                        ->orWhereColumn('environment_notification_notified_at', '<', 'environment_entered_at');
                })
                ->update(['environment_notification_notified_at' => $entryAt]);
        }

        if (! $claimedCompletion && ! $claimedEntry) {
            return 'not_requested';
        }
        if ($claimedCompletion) {
            $task->production_notified_at = $claimedAt;
        }
        if ($claimedEntry) {
            $task->environment_notification_notified_at = $entryAt;
        }

        try {
            $title = $task->title;
            $code = 'NX-'.str_pad((string) $task->id, 3, '0', STR_PAD_LEFT);
            $fields = $task->notification_fields ?? ['title', 'code', 'status'];
            $legacyIntro = '¡Listo! 🚀 La tarea ya está en Producción.';
            $environmentLabel = Task::ENVIRONMENTS[$task->environment] ?? $task->environment;
            $heading = $claimedCompletion ? 'Producción completada' : 'Tarea movida a '.$environmentLabel;
            $intro = trim($task->notification_message ?: ($claimedCompletion ? $legacyIntro : 'La tarea pasó a '.$environmentLabel.'.'));
            if (! $claimedCompletion && $intro === $legacyIntro) {
                $intro = 'La tarea pasó a '.$environmentLabel.'.';
            }
            $details = [];
            if (in_array('client', $fields, true) && $task->client_id) {
                $client = DB::table('clients')->where('workspace_id', $task->workspace_id)->where('id', $task->client_id)->first();
                if ($client) {
                    $details['client'] = $client->name.($client->code ? ' · '.$client->code : '');
                }
            }
            if (in_array('project', $fields, true) && $task->project_id) {
                $project = DB::table('projects')->where('workspace_id', $task->workspace_id)->where('id', $task->project_id)->first();
                if ($project) {
                    $details['project'] = $project->name;
                }
            }
            if (in_array('code', $fields, true)) {
                $details['code'] = $code;
            }
            if (in_array('title', $fields, true)) {
                $details['title'] = $title;
            }
            if (in_array('description', $fields, true) && filled($task->description)) {
                $details['description'] = $task->description;
            }
            if (in_array('status', $fields, true)) {
                $details['status'] = $environmentLabel.' · '.(Task::STATUSES[$task->status] ?? $task->status);
            }
            if (in_array('checklist', $fields, true) && count($task->checklist ?? [])) {
                $details['checklist'] = $task->checklist;
            }
            $attachments = in_array('attachments', $fields, true) ? $task->attachments : collect();
            if ($attachments->count()) {
                $details['attachments'] = $attachments->pluck('name')->all();
            }
            $labels = ['client' => 'Cliente', 'project' => 'Proyecto', 'code' => 'Código', 'title' => 'Tarea', 'description' => 'Descripción', 'status' => 'Ambiente/Estado', 'checklist' => 'Checklist', 'attachments' => 'Archivos adjuntos'];
            $body = $intro.(count($details) ? "\n\n".collect($details)->map(fn ($value, $label) => ($labels[$label] ?? ucfirst($label)).': '.(is_array($value) ? "\n".collect($value)->map(fn ($step) => (($step['done'] ?? false) ? '☑' : '☐').' '.($step['text'] ?? ''))->join("\n") : $value))->join("\n\n") : '')."\n\nEste aviso fue enviado automáticamente por Nexo.";
            $html = view('emails.production-completed', compact('intro', 'title', 'code', 'details', 'heading'))->render();
            $subject = $claimedCompletion ? "{$title} ya está en Producción" : "{$title} pasó a {$environmentLabel}";
            Mail::send([], [], function ($message) use ($task, $subject, $body, $html, $attachments) {
                $message->to($task->notify_emails)
                    ->subject($subject)
                    ->text($body)
                    ->html($html);
                foreach ($attachments as $attachment) {
                    $path = Storage::disk('local')->path($attachment->path);
                    if (is_file($path)) {
                        $message->attach($path, ['as' => $attachment->name]);
                    }
                }
            });
            Content::log($task->user_id, $claimedCompletion ? 'Aviso de producción enviado' : 'Aviso de cambio de ambiente enviado', 'task', $task->id, $task->title);

            return 'sent';
        } catch (\Throwable $e) {
            if ($claimedCompletion) {
                Task::whereKey($task->id)->where('production_notified_at', $claimedAt)->update(['production_notified_at' => null]);
                $task->production_notified_at = null;
            }
            if ($claimedEntry) {
                Task::whereKey($task->id)->where('environment_entered_at', $entryAt)->where('environment_notification_notified_at', $entryAt)->update(['environment_notification_notified_at' => null]);
                $task->environment_notification_notified_at = null;
            }
            Log::error('No se pudo enviar la notificación por correo', ['task_id' => $task->id, 'error' => $e->getMessage()]);

            return 'failed';
        }
    }

    private function resetProductionNotificationIfNeeded(Task $task): void
    {
        if ($task->environment !== 'production' || $task->status !== 'done') {
            $task->forceFill(['production_notified_at' => null, 'production_message_notified_at' => null])->saveQuietly();
        }
    }

    private function normalizeEmails(string $value): array
    {
        return collect(preg_split('/[,;\s]+/u', $value))->map(fn ($email) => mb_strtolower(trim($email)))->filter()->unique()->values()->all();
    }

    public function checklist(Request $r, Task $task)
    {

        $this->authorizeTask($r, $task);
        $data = $r->validate(['index' => 'required|integer|min:0', 'done' => 'required|boolean']);

        DB::transaction(function () use ($task, $data) {

            $locked = Task::whereKey($task->id)->lockForUpdate()->firstOrFail();
            $items = $locked->checklist ?? [];

            abort_unless(isset($items[$data['index']]), 404);

            $items[$data['index']]['done'] = (bool) $data['done'];
            $locked->update(['checklist' => $items]);

        });

        Content::log($r->user()->id, 'Lista de entrega actualizada', 'task', $task->id, $task->title);

        return response()->json($task->fresh()->load('attachments'));

    }

    public function download(Request $r, Attachment $attachment)
    {

        $this->authorizeTask($r, $attachment->task);

        if ($r->boolean('preview')) {
            $extension = strtolower(pathinfo($attachment->name, PATHINFO_EXTENSION));
            $mime = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif'][$extension] ?? null;
            if ($mime) {
                return Storage::disk('local')->response($attachment->path, $attachment->name, [
                    'Content-Type' => $mime,
                    'Content-Disposition' => 'inline; filename="'.addslashes($attachment->name).'"',
                    'X-Content-Type-Options' => 'nosniff',
                    'Cache-Control' => 'private, no-store',
                ], 'inline');
            }
        }

        return Storage::disk('local')->download($attachment->path, $attachment->name, ['X-Content-Type-Options' => 'nosniff']);

    }

    public function deleteAttachment(Request $r, Attachment $attachment)
    {

        $this->authorizeTask($r, $attachment->task);
        Content::log($r->user()->id, 'Adjunto eliminado', 'task', $attachment->task_id, $attachment->task->title);
        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();

        return response()->noContent();

    }

    public function destroy(Request $r, Task $task)
    {

        $this->authorizeTask($r, $task);

        foreach ($task->attachments as $file) {
            Storage::disk('local')->delete($file->path);
        }

        foreach (Media::where('task_id', $task->id)->get() as $media) {
            Storage::disk('local')->delete($media->path);
        }

        Content::log($r->user()->id, 'Eliminado', 'task', $task->id, $task->title);

        $task->delete();

        return response()->noContent();

    }
}
