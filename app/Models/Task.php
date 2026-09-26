<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    public const STATUSES = ['pending' => 'Pendiente', 'development' => 'En desarrollo', 'review' => 'En revisión', 'done' => 'Completada'];

    public const ENVIRONMENTS = ['backlog' => 'Backlog', 'local' => 'Local', 'development' => 'Desarrollo', 'qa' => 'QA', 'certification' => 'Certificación', 'production' => 'Producción'];

    public const PRIORITIES = ['low' => 'Baja', 'normal' => 'Normal', 'high' => 'Alta', 'urgent' => 'Urgente'];

    protected $guarded = ['id'];

    public static function allowedStatuses(string $environment): array
    {
        return match ($environment) {
            'backlog' => ['pending'], 'certification', 'production' => ['review', 'done'], 'qa' => ['review', 'done'], default => ['development', 'review', 'done']
        };
    }

    protected function casts(): array
    {
        return ['checklist' => 'array', 'notes_blocks' => 'array', 'description_blocks' => 'array', 'tags' => 'array', 'notify_emails' => 'array', 'notify_message_emails' => 'array', 'notification_fields' => 'array', 'email_notification_events' => 'array', 'due_date' => 'date', 'estimated_delivery_at' => 'datetime', 'production_notified_at' => 'datetime', 'production_message_notified_at' => 'datetime', 'environment_entered_at' => 'datetime', 'environment_notification_notified_at' => 'datetime', 'production_returned_at' => 'datetime', 'environment_returned_at' => 'datetime', 'environment_return_resolved' => 'boolean', 'is_fire' => 'boolean', 'notify_on_production' => 'boolean', 'notify_message_on_production' => 'boolean'];
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }
}
