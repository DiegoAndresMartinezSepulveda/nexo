<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditLogger
{
    /**
     * Append an audit event without ever accepting secrets or free-form payloads.
     * Metadata is intentionally restricted to short, non-content identifiers.
     */
    public static function record(
        Request $request,
        string $event,
        string $action,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?string $subjectName = null,
        ?int $workspaceId = null,
        array $metadata = [],
        ?User $actorOverride = null,
    ): void {
        $actor = $actorOverride ?? $request->user();
        $workspaceName = $workspaceId
            ? DB::table('workspaces')->where('id', $workspaceId)->value('name')
            : null;

        $safeMetadata = [];
        foreach (['fields', 'role', 'workspace_ids', 'from', 'to', 'status', 'environment', 'result'] as $key) {
            if (! array_key_exists($key, $metadata)) {
                continue;
            }

            $value = $metadata[$key];
            if ($key === 'fields' && is_array($value)) {
                $safeMetadata[$key] = array_values(array_intersect($value, [
                    'title', 'description', 'description_blocks', 'notes_blocks', 'blocks', 'status',
                    'environment', 'priority', 'client_id', 'project_id', 'due_date', 'estimated_delivery_at',
                    'checklist', 'attachments', 'visibility', 'tags', 'category', 'color', 'pinned',
                    'archived', 'diagram', 'is_fire', 'notify_on_production', 'email_notification_events',
                    'notify_message_on_production',
                ]));
            } elseif ($key === 'workspace_ids' && is_array($value)) {
                $safeMetadata[$key] = array_values(array_slice(array_map('intval', $value), 0, 50));
            } elseif (is_string($value) || is_numeric($value) || is_bool($value)) {
                $safeMetadata[$key] = is_string($value) ? mb_substr($value, 0, 100) : $value;
            }
        }

        DB::table('audit_logs')->insert([
            'workspace_id' => $workspaceId,
            'workspace_name' => $workspaceName ? mb_substr($workspaceName, 0, 120) : null,
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name ? mb_substr($actor->name, 0, 120) : null,
            'actor_email' => $actor?->email ? mb_substr($actor->email, 0, 255) : null,
            'event' => mb_substr($event, 0, 100),
            'action' => mb_substr($action, 0, 255),
            'subject_type' => $subjectType ? mb_substr($subjectType, 0, 40) : null,
            'subject_id' => $subjectId,
            'subject_name' => $subjectName ? mb_substr($subjectName, 0, 255) : null,
            'metadata' => $safeMetadata ? json_encode($safeMetadata, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
            'ip_address' => $request->ip() ? mb_substr($request->ip(), 0, 45) : null,
            'user_agent' => $request->userAgent() ? mb_substr($request->userAgent(), 0, 500) : null,
            'created_at' => now(),
        ]);
    }
}
