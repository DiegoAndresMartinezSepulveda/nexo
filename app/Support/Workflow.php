<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Workflow
{
    public static function defaults(): array
    {
        return [
            'statuses' => [
                ['key' => 'pending', 'label' => 'Pendiente', 'active' => true],
                ['key' => 'development', 'label' => 'En desarrollo', 'active' => true],
                ['key' => 'review', 'label' => 'En revisión', 'active' => true],
                ['key' => 'done', 'label' => 'Completada', 'active' => true],
            ],
            'environments' => [
                ['key' => 'backlog', 'label' => 'Backlog', 'active' => true, 'allowed_statuses' => ['pending']],
                ['key' => 'local', 'label' => 'Local', 'active' => true, 'allowed_statuses' => ['development', 'review', 'done']],
                ['key' => 'development', 'label' => 'Desarrollo', 'active' => true, 'allowed_statuses' => ['development', 'review', 'done']],
                ['key' => 'qa', 'label' => 'QA', 'active' => true, 'allowed_statuses' => ['review', 'done']],
                ['key' => 'certification', 'label' => 'Certificación', 'active' => true, 'allowed_statuses' => ['review', 'done']],
                ['key' => 'production', 'label' => 'Producción', 'active' => true, 'allowed_statuses' => ['review', 'done']],
            ],
        ];
    }

    public static function forWorkspace(int $workspaceId): array
    {
        $raw = DB::table('workspaces')->where('id', $workspaceId)->value('workflow');
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        return self::normalise(is_array($raw) ? $raw : self::defaults(), $workspaceId);
    }

    public static function save(int $workspaceId, array $workflow): array
    {
        $workflow = self::validate($workflow, $workspaceId);
        DB::table('workspaces')->where('id', $workspaceId)->update(['workflow' => json_encode($workflow), 'updated_at' => now()]);

        return $workflow;
    }

    public static function environmentKeys(array $workflow): array
    {
        return array_column($workflow['environments'], 'key');
    }

    public static function statusKeys(array $workflow): array
    {
        return array_column($workflow['statuses'], 'key');
    }

    public static function environmentLabel(array $workflow, string $key): string
    {
        foreach ($workflow['environments'] as $environment) {
            if ($environment['key'] === $key) {
                return $environment['label'];
            }
        }

        return $key;
    }

    public static function statusLabel(array $workflow, string $key): string
    {
        foreach ($workflow['statuses'] as $status) {
            if ($status['key'] === $key) {
                return $status['label'];
            }
        }

        return $key;
    }

    public static function allowedStatuses(array $workflow, string $environment): array
    {
        foreach ($workflow['environments'] as $item) {
            if ($item['key'] === $environment) {
                return $item['allowed_statuses'];
            }
        }

        return [];
    }

    public static function environmentIsActive(array $workflow, string $environment): bool
    {
        foreach ($workflow['environments'] as $item) {
            if ($item['key'] === $environment) {
                return (bool) $item['active'];
            }
        }

        return false;
    }

    public static function statusIsActive(array $workflow, string $status): bool
    {
        foreach ($workflow['statuses'] as $item) {
            if ($item['key'] === $status) {
                return (bool) $item['active'];
            }
        }

        return false;
    }

    private static function validate(array $workflow, int $workspaceId): array
    {
        $statuses = $workflow['statuses'] ?? null;
        $environments = $workflow['environments'] ?? null;
        if (!is_array($statuses) || !is_array($environments) || !count($statuses) || !count($environments) || count($statuses) > 20 || count($environments) > 20) {
            throw ValidationException::withMessages(['workflow' => 'Define entre 1 y 20 estados y ambientes.']);
        }

        $keys = [];
        $cleanStatuses = [];
        foreach ($statuses as $status) {
            $key = trim((string) ($status['key'] ?? ''));
            $label = trim((string) ($status['label'] ?? ''));
            if (!preg_match('/^[a-z][a-z0-9_]{1,39}$/', $key) || $label === '' || mb_strlen($label) > 60 || isset($keys[$key])) {
                throw ValidationException::withMessages(['workflow' => 'Cada estado necesita un identificador único y un nombre de hasta 60 caracteres.']);
            }
            $keys[$key] = true;
            $cleanStatuses[] = ['key' => $key, 'label' => $label, 'active' => (bool) ($status['active'] ?? true)];
        }

        $environmentKeys = [];
        $cleanEnvironments = [];
        foreach ($environments as $environment) {
            $key = trim((string) ($environment['key'] ?? ''));
            $label = trim((string) ($environment['label'] ?? ''));
            $allowed = array_values(array_unique(array_filter($environment['allowed_statuses'] ?? [], fn ($status) => isset($keys[$status]))));
            if (!preg_match('/^[a-z][a-z0-9_]{1,39}$/', $key) || $label === '' || mb_strlen($label) > 60 || isset($environmentKeys[$key])) {
                throw ValidationException::withMessages(['workflow' => 'Cada ambiente necesita un identificador único y un nombre de hasta 60 caracteres.']);
            }
            if (!count($allowed)) {
                throw ValidationException::withMessages(['workflow' => "El ambiente {$label} debe aceptar al menos un estado."]);
            }
            $environmentKeys[$key] = true;
            $cleanEnvironments[] = ['key' => $key, 'label' => $label, 'active' => (bool) ($environment['active'] ?? true), 'allowed_statuses' => $allowed];
        }

        $workflow = ['statuses' => $cleanStatuses, 'environments' => $cleanEnvironments];

        return self::normalise($workflow, $workspaceId);
    }

    private static function normalise(array $workflow, int $workspaceId): array
    {
        $defaults = self::defaults();
        $statuses = array_values(array_filter($workflow['statuses'] ?? [], fn ($item) => is_array($item) && isset($item['key'], $item['label'])));
        $environments = array_values(array_filter($workflow['environments'] ?? [], fn ($item) => is_array($item) && isset($item['key'], $item['label'])));
        if (!count($statuses) || !count($environments)) {
            return self::normalise($defaults, $workspaceId);
        }

        $statusKeys = array_column($statuses, 'key');
        foreach (DB::table('tasks')->where('workspace_id', $workspaceId)->distinct()->pluck('status') as $key) {
            if (!in_array($key, $statusKeys, true)) {
                $statuses[] = ['key' => $key, 'label' => $key, 'active' => false];
                $statusKeys[] = $key;
            }
        }
        $environmentKeys = array_column($environments, 'key');
        foreach (DB::table('tasks')->where('workspace_id', $workspaceId)->distinct()->pluck('environment') as $key) {
            if (!in_array($key, $environmentKeys, true)) {
                $environments[] = ['key' => $key, 'label' => $key, 'active' => false, 'allowed_statuses' => $statusKeys];
            }
        }

        return ['statuses' => $statuses, 'environments' => array_map(fn ($item) => [
            'key' => $item['key'], 'label' => $item['label'], 'active' => (bool) ($item['active'] ?? true),
            'allowed_statuses' => array_values(array_intersect($item['allowed_statuses'] ?? [], $statusKeys)),
        ], $environments)];
    }
}
