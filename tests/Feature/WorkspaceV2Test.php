<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkspaceV2Test extends TestCase
{
    use RefreshDatabase;

    private function entry(array $extra = []): array
    {
        return array_merge(['kind' => 'note', 'title' => 'Revisar producción', 'color' => 'yellow', 'pinned' => false, 'archived' => false, 'blocks' => [['type' => 'text', 'html' => '<p><b>Importante</b>: revisar el correo.</p>']], 'tags' => ['producción']], $extra);
    }

    public function test_formatted_notes_are_sanitized_and_can_be_pinned_archived_and_searched(): void
    {
        $this->actingAs(User::factory()->create());
        $r = $this->postJson('/api/entries', $this->entry(['blocks' => [['type' => 'text', 'html' => '<b>Negrita</b><i>Cursiva</i><font color="#ff0000" size="5" face="Georgia">Texto</font><script>alert(1)</script><img src=x onerror=alert(1)>']]]))->assertCreated();
        $id = $r->json('id');
        $html = $r->json('blocks.0.html');
        $this->assertStringContainsString('<b>Negrita</b>', $html);
        $this->assertStringContainsString('#ff0000', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->putJson('/api/entries/'.$id, $this->entry(['pinned' => true, 'archived' => true, 'color' => 'red']))->assertOk()->assertJsonPath('pinned', true)->assertJsonPath('color', 'red');
        $this->getJson('/api/entries?kind=note')->assertJsonCount(0);
        $this->getJson('/api/entries?kind=note&archived=1&q=producción')->assertJsonCount(1);
        $this->getJson('/api/history')->assertJsonCount(2, 'data');
    }

    public function test_library_uploads_are_private_persistent_and_removed_with_entry(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $upload = $this->post('/api/media', ['file' => UploadedFile::fake()->createWithContent('validacion.sql', 'SELECT id FROM clientes;')], ['Accept' => 'application/json'])->assertCreated();
        $id = $upload->json('id');
        $media = Media::findOrFail($id);
        $entry = $this->postJson('/api/entries', $this->entry(['kind' => 'library', 'category' => 'SQL', 'attachment_ids' => [$id]]))->assertCreated()->json('id');
        $this->getJson('/api/entries?kind=library')->assertJsonCount(1);
        $this->getJson('/api/entries?kind=library&type=sql')->assertJsonCount(1);
        $this->getJson('/api/entries?kind=library&category=SQL')->assertJsonCount(1);
        $this->getJson('/api/entries?kind=library&tag=producción')->assertJsonCount(1);
        $this->getJson('/api/entries?kind=library&type=sql&category=SQL&tag=producción')->assertJsonCount(1);
        $this->get('/api/media/'.$id)->assertDownload('validacion.sql');
        $this->actingAs(User::factory()->create());
        $this->getJson('/api/media/'.$id)->assertNotFound();
        $this->getJson('/api/entries/'.$entry)->assertNotFound();
        $this->putJson('/api/entries/'.$entry, $this->entry(['kind' => 'library']))->assertNotFound();
        $this->deleteJson('/api/entries/'.$entry)->assertNotFound();
        $this->actingAs($owner);
        $this->deleteJson('/api/entries/'.$entry)->assertNoContent();
        Storage::disk('local')->assertMissing($media->path);
    }

    public function test_inline_images_belong_to_only_one_record_and_owner(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6mS8AAAAASUVORK5CYII=');
        $id = $this->post('/api/media', ['file' => UploadedFile::fake()->createWithContent('captura.png', $png)], ['Accept' => 'application/json'])->assertCreated()->json('id');
        $r = $this->postJson('/api/entries', $this->entry(['blocks' => [['type' => 'image', 'media_id' => $id]]]))->assertCreated();
        $this->get('/api/media/'.$id.'?preview=1')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->postJson('/api/entries', $this->entry(['blocks' => [['type' => 'image', 'media_id' => $id]]]))->assertUnprocessable();
        $this->actingAs(User::factory()->create());
        $this->postJson('/api/entries', $this->entry(['blocks' => [['type' => 'image', 'media_id' => $id]]]))->assertUnprocessable();
        $this->getJson('/api/media/'.$id.'?preview=1')->assertNotFound();
        $this->assertDatabaseCount('entries', 1);
        $this->actingAs($owner);
        $this->putJson('/api/entries/'.$r->json('id'), $this->entry())->assertOk();
        $this->assertDatabaseMissing('media', ['id' => $id]);
    }

    public function test_environment_rules_and_incident_flag(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u);
        $payload = ['title' => 'Incendio en clientes', 'status' => 'development', 'environment' => 'local', 'priority' => 'normal', 'is_fire' => true, 'description_blocks' => [['type' => 'text', 'html' => '<h2>Objetivo</h2><ul><li>Corregir</li></ul><script>alert(1)</script>']], 'notes_blocks' => [['type' => 'text', 'html' => '<b>Revisar</b>']]];
        $id = $this->postJson('/api/tasks', $payload)->assertCreated()->assertJsonPath('is_fire', true)->assertJsonPath('description_blocks.0.html', '<h2>Objetivo</h2><ul><li>Corregir</li></ul>')->json('id');
        $this->patchJson("/api/tasks/$id/move", ['environment' => 'certification', 'status' => 'development'])->assertUnprocessable();
        $this->patchJson("/api/tasks/$id/move", ['environment' => 'certification', 'status' => 'review'])->assertOk();
        $this->patchJson("/api/tasks/$id/status", ['status' => 'done'])->assertOk();
        $this->patchJson("/api/tasks/$id/move", ['environment' => 'production', 'status' => 'done'])->assertOk();
        $this->patchJson("/api/tasks/$id/move", ['environment' => 'qa', 'status' => 'pending'])->assertUnprocessable();
        $this->patchJson("/api/tasks/$id/move", ['environment' => 'development', 'status' => 'pending'])->assertUnprocessable();
        $this->putJson('/api/tasks/'.$id, $payload + ['client_id' => null])->assertOk()->assertJsonPath('notes_blocks.0.html', '<b>Revisar</b>');
    }

    public function test_production_email_uses_the_selected_task_fields(): void
    {
        $this->actingAs(User::factory()->create());
        $client = $this->postJson('/api/catalog/clients', ['name' => 'Safin', 'code' => '9998'])->assertOk()->json('id');
        $project = $this->postJson('/api/catalog/projects', ['name' => 'Portal', 'client_id' => $client])->assertOk()->json('id');
        $payload = ['title' => 'Publicar módulo', 'description' => 'Disponible para usuarios', 'status' => 'done', 'environment' => 'production', 'priority' => 'high', 'client_id' => $client, 'project_id' => $project, 'checklist_text' => "Compilar\nValidar", 'notify_on_production' => true, 'notify_emails' => 'persona@example.com', 'notification_message' => 'Hola, la entrega está lista.', 'notify_include_client' => true, 'notify_include_project' => true, 'notify_include_title' => true, 'notify_include_description' => false, 'notify_include_code' => false, 'notify_include_status' => true, 'notify_include_checklist' => true];
        $response = $this->postJson('/api/tasks', $payload)->assertCreated()->assertJsonPath('notification', 'sent');
        $this->assertNotNull(Task::findOrFail($response->json('id'))->production_notified_at);
        $messages = Mail::mailer()->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $body = $messages->first()->getOriginalMessage()->getTextBody();
        $this->assertStringContainsString('Hola, la entrega está lista.', $body);
        $this->assertStringContainsString('Cliente: Safin · 9998', $body);
        $this->assertStringContainsString('Proyecto: Portal', $body);
        $this->assertStringContainsString('Tarea: Publicar módulo', $body);
        $this->assertStringContainsString('☐ Compilar', $body);
        $this->assertStringNotContainsString('Descripción:', $body);
        $this->assertStringNotContainsString('Código:', $body);
    }

    public function test_catalogs_are_owner_scoped_and_project_client_must_match(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $client = $this->postJson('/api/catalog/clients', ['name' => 'Cliente A'])->assertOk()->json('id');
        $project = $this->postJson('/api/catalog/projects', ['name' => 'Proyecto A', 'client_id' => $client])->assertOk()->json('id');
        $this->postJson('/api/entries', $this->entry(['project_id' => $project]))->assertUnprocessable();
        $entry = $this->postJson('/api/entries', $this->entry(['project_id' => $project, 'client_id' => $client]))->assertCreated()->json('id');
        $this->actingAs(User::factory()->create());
        $this->getJson('/api/catalog')->assertJsonCount(0, 'clients');
        $this->postJson('/api/entries', $this->entry(['project_id' => $project, 'client_id' => $client]))->assertUnprocessable();
        $this->putJson('/api/catalog/clients/'.$client, ['name' => 'Otro'])->assertNotFound();
        $this->deleteJson('/api/catalog/projects/'.$project)->assertNotFound();
        $this->actingAs($owner);
        $this->deleteJson('/api/catalog/clients/'.$client)->assertNoContent();
        $this->getJson('/api/entries/'.$entry)->assertOk()->assertJsonPath('client_id', null);
    }

    public function test_dangerous_uploads_and_guest_access_are_rejected(): void
    {
        $this->postJson('/api/entries', $this->entry())->assertUnauthorized();
        $this->getJson('/api/catalog')->assertUnauthorized();
        $this->getJson('/api/history')->assertUnauthorized();
        $this->actingAs(User::factory()->create());
        Storage::fake('local');
        $this->post('/api/media', ['file' => UploadedFile::fake()->createWithContent('evil.html', '<script>alert(1)</script>')], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->post('/api/media', ['file' => UploadedFile::fake()->createWithContent('evil.svg', '<svg onload="alert(1)"></svg>')], ['Accept' => 'application/json'])->assertUnprocessable();
    }
}
