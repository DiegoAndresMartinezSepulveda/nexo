<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditLogsTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_changes_are_snapshotted_and_admin_can_filter_and_export(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $workspaceId = (int) DB::table('workspace_user')->where('user_id', $admin->id)->value('workspace_id');
        $workspaceName = DB::table('workspaces')->where('id', $workspaceId)->value('name');
        $this->actingAs($admin)->withHeader('X-Workspace-ID', (string) $workspaceId);

        $this->postJson('/api/tasks', [
            'title' => 'Revisar entrega',
            'description' => 'Este texto no debe aparecer en los detalles de auditoría.',
            'environment' => 'backlog',
            'status' => 'pending',
            'priority' => 'normal',
        ])->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => $workspaceId,
            'actor_id' => $admin->id,
            'actor_name' => $admin->name,
            'actor_email' => $admin->email,
            'event' => 'task.created',
            'action' => 'Creado',
            'subject_name' => 'Revisar entrega',
            'ip_address' => '127.0.0.1',
        ]);

        $this->getJson('/api/audit-logs?event=task.created&q=Revisar')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.workspace_name', $workspaceName)
            ->assertJsonPath('data.0.actor_email', $admin->email)
            ->assertJsonMissing(['description' => 'Este texto no debe aparecer en los detalles de auditoría.']);

        $this->get('/api/audit-logs/export?event=task.created')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition');
    }

    public function test_readers_cannot_view_or_export_audit_logs(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $this->actingAs($reader);

        $this->getJson('/api/audit-logs')->assertForbidden();
        $this->getJson('/api/audit-logs/export')->assertForbidden();
    }

    public function test_csv_export_escapes_spreadsheet_formulas(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        DB::table('audit_logs')->insert([
            'event' => 'admin.user.updated',
            'action' => 'Usuario actualizado',
            'subject_name' => '=HYPERLINK("https://example.invalid")',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get('/api/audit-logs/export');

        $this->assertStringContainsString("'=HYPERLINK", $response->streamedContent());
    }
}
