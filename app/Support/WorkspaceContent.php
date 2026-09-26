<?php

namespace App\Support;

use App\Models\Media;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorkspaceContent
{
    public static function rules(int $userId, string $field = 'blocks'): array
    {
        return [
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')->where('workspace_id', Spaces::id())],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where('workspace_id', Spaces::id())],
            $field => 'sometimes|nullable|array|max:150',
            "$field.*.type" => ['required', Rule::in(['text', 'image'])],
            "$field.*.html" => 'nullable|string|max:60000',
            "$field.*.text" => 'nullable|string|max:30000',
            "$field.*.media_id" => 'nullable|integer',
            "$field.*.caption" => 'nullable|string|max:500',
        ];
    }

    public static function validateProject(array $data): void
    {
        if (! empty($data['project_id'])) {
            $project = DB::table('projects')->find($data['project_id']);
            if ((int) $project->client_id !== (int) ($data['client_id'] ?? 0)) {
                throw ValidationException::withMessages(['project_id' => 'El proyecto debe pertenecer al cliente seleccionado.']);
            }
        }
    }

    public static function blocks(?array $blocks): array
    {
        return collect($blocks ?? [])->map(function ($b) {
            if ($b['type'] === 'image') {
                if (empty($b['media_id'])) {
                    throw ValidationException::withMessages(['blocks' => 'La imagen no terminó de subir.']);
                }

                return ['type' => 'image', 'media_id' => (int) $b['media_id'], 'caption' => $b['caption'] ?? ''];
            }
            $config = \HTMLPurifier_Config::createDefault();
            $config->set('Cache.DefinitionImpl', null);
            $config->set('HTML.Allowed', 'p[style],div[style],span[class|style],b,strong,i,em,u,s,strike,br,h2,h3,ul[class],ol,li,blockquote,pre,code,font[color|size|face]');
            $config->set('CSS.AllowedProperties', ['color', 'background-color', 'font-size', 'font-family', 'text-align', 'font-weight', 'font-style', 'text-decoration']);
            $html = (new \HTMLPurifier($config))->purify($b['html'] ?? nl2br(htmlspecialchars($b['text'] ?? '', ENT_QUOTES, 'UTF-8')));

            return ['type' => 'text', 'html' => $html, 'text' => html_entity_decode(strip_tags(str_replace(['</p>', '</div>', '<br />'], "\n", $html)), ENT_QUOTES, 'UTF-8')];
        })->values()->all();
    }

    // Called inside the record's transaction. Never associate another user's asset.
    public static function bind(int $userId, string $foreignKey, int $id, array $blocks, array $attachmentIds = []): void
    {
        $images = collect($blocks)->where('type', 'image')->pluck('media_id');
        $ids = $images->merge($attachmentIds)->unique()->values();
        $otherKey = $foreignKey === 'task_id' ? 'entry_id' : 'task_id';
        $files = Media::whereIn('id', $ids)->lockForUpdate()->get();
        if ($files->count() !== $ids->count() || $files->contains(fn ($m) => $m->workspace_id !== Spaces::id() || $m->$otherKey !== null || ($m->$foreignKey !== null && $id !== $m->$foreignKey))) {
            throw ValidationException::withMessages(['blocks' => 'Uno de los archivos no está disponible para esta entrada.']);
        }
        if ($files->contains(fn ($m) => $images->contains($m->id) && ! in_array($m->mime, ['image/png', 'image/jpeg', 'image/webp', 'image/gif']))) {
            throw ValidationException::withMessages(['blocks' => 'Solo se permiten imágenes en los bloques de captura.']);
        }
        $removed = Media::where('workspace_id', Spaces::id())->where($foreignKey, $id)->whereNotIn('id', $ids)->get();
        foreach ($removed as $m) {
            $m->delete();
            DB::afterCommit(fn () => Storage::disk('local')->delete($m->path));
        }
        Media::whereIn('id', $ids)->update([$foreignKey => $id]);
    }

    public static function log(int $userId, string $action, string $type, int $id, string $title): void
    {
        DB::table('activities')->insert(['workspace_id' => Spaces::id(), 'user_id' => $userId, 'action' => $action, 'subject_type' => $type, 'subject_id' => $id, 'title' => mb_substr($title, 0, 255), 'created_at' => now(), 'updated_at' => now()]);
    }
}
