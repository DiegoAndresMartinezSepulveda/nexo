<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Task extends Model {
    public const STATUSES = ['pending' => 'Pendiente', 'development' => 'En desarrollo', 'review' => 'En revisión', 'done' => 'Completada'];
    public const ENVIRONMENTS = ['local' => 'Local', 'development' => 'Desarrollo', 'qa' => 'QA', 'certification' => 'Certificación', 'production' => 'Producción'];
    public const PRIORITIES = ['low' => 'Baja', 'normal' => 'Normal', 'high' => 'Alta', 'urgent' => 'Urgente'];
    protected $guarded = ['id'];
    public static function allowedStatuses(string $environment): array {
        return match ($environment) { 'certification' => ['review'], 'production', 'qa' => ['review','done'], default => array_keys(self::STATUSES) };
    }
    protected function casts(): array { return ['checklist' => 'array', 'notes_blocks' => 'array', 'due_date' => 'date', 'is_fire' => 'boolean']; }
    public function attachments(): HasMany { return $this->hasMany(Attachment::class); }
}
