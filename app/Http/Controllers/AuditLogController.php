<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin', 403);
    }

    private function filtered(Request $request): Builder
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:160'],
            'event' => ['nullable', 'string', 'max:100'],
            'actor_id' => ['nullable', 'integer', 'min:1'],
            'workspace_id' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        return DB::table('audit_logs')
            ->when($filters['q'] ?? null, fn (Builder $query, string $term) => $query->where(function (Builder $query) use ($term): void {
                $like = '%'.$term.'%';
                $query->where('actor_name', 'like', $like)
                    ->orWhere('actor_email', 'like', $like)
                    ->orWhere('action', 'like', $like)
                    ->orWhere('subject_name', 'like', $like)
                    ->orWhere('ip_address', 'like', $like);
            }))
            ->when($filters['event'] ?? null, fn (Builder $query, string $event) => $query->where('event', $event))
            ->when($filters['actor_id'] ?? null, fn (Builder $query, int $id) => $query->where('actor_id', $id))
            ->when($filters['workspace_id'] ?? null, fn (Builder $query, int $id) => $query->where('workspace_id', $id))
            ->when($filters['from'] ?? null, fn (Builder $query, string $date) => $query->where('created_at', '>=', $date.' 00:00:00'))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date) => $query->where('created_at', '<=', $date.' 23:59:59'));
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        abort_unless(Schema::hasTable('audit_logs'), 503, 'La auditoría aún no está disponible. Ejecuta las migraciones pendientes.');

        return response()->json($this->filtered($request)->orderByDesc('id')->paginate(50));
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizeAdmin($request);
        abort_unless(Schema::hasTable('audit_logs'), 503, 'La auditoría aún no está disponible. Ejecuta las migraciones pendientes.');
        $rows = $this->filtered($request)->orderByDesc('id')->limit(10000)->get();

        return response()->streamDownload(function () use ($rows): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Fecha y hora', 'Persona', 'Correo', 'Espacio', 'Acción', 'Tipo', 'Elemento', 'Dirección IP', 'Detalles']);

            foreach ($rows as $row) {
                $values = [
                    $row->created_at,
                    $row->actor_name,
                    $row->actor_email,
                    $row->workspace_name,
                    $row->action,
                    $row->subject_type,
                    $row->subject_name,
                    $row->ip_address,
                    $row->metadata,
                ];
                fputcsv($stream, array_map(fn ($value) => $this->spreadsheetSafe((string) ($value ?? '')), $values));
            }

            fclose($stream);
        }, 'nexo-auditoria-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function spreadsheetSafe(string $value): string
    {
        return preg_match('/^[\s]*[=+@\-]/u', $value) ? "'".$value : $value;
    }
}
