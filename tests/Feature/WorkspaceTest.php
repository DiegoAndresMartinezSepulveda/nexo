<?php
namespace Tests\Feature;
use App\Models\{Task, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkspaceTest extends TestCase {
    use RefreshDatabase;
    private function payload(array $changes = []): array {
        return array_merge(['title' => 'Crear módulo de clientes', 'description' => 'Validar correo y guardar el cliente.', 'status' => 'pending', 'environment' => 'backlog', 'priority' => 'high', 'checklist_text' => "Crear tabla\nSubir archivos"], $changes);
    }
    public function test_guests_cannot_read_or_modify_tasks(): void {
        $this->getJson('/api/tasks')->assertUnauthorized();
        $this->postJson('/api/tasks', $this->payload())->assertUnauthorized();
        $this->getJson('/api/session')->assertJsonPath('user', null);
    }
    public function test_session_login_and_logout(): void {
        $user = User::factory()->create(['password' => bcrypt('UnaClaveSegura123!')]);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'incorrecta'])->assertUnprocessable();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'UnaClaveSegura123!'])->assertOk()->assertJsonPath('user.id', $user->id);
        $this->assertAuthenticatedAs($user);
        $this->postJson('/api/logout')->assertNoContent();
        $this->assertGuest();
    }
    public function test_task_lifecycle_with_checklist_and_private_attachment(): void {
        Storage::fake('local'); $this->actingAs(User::factory()->create());
        $response = $this->post('/api/tasks', $this->payload(['files' => [UploadedFile::fake()->createWithContent('requerimiento.txt', 'Criterios de aceptación')]]), ['Accept' => 'application/json'])->assertCreated();
        $id = $response->json('id'); $task = Task::findOrFail($id); $file = $task->attachments()->firstOrFail();
        Storage::disk('local')->assertExists($file->path);
        $this->get('/api/attachments/'.$file->id)->assertDownload('requerimiento.txt');
        $this->patchJson("/api/tasks/$id/checklist", ['index' => 0, 'done' => true])->assertOk()->assertJsonPath('checklist.0.done', true);
        $this->putJson("/api/tasks/$id", $this->payload(['environment' => 'certification', 'status' => 'review']))->assertOk()->assertJsonPath('checklist.0.done', true);
        $this->patchJson("/api/tasks/$id/status", ['status' => 'review'])->assertOk()->assertJsonPath('status', 'review');
        $this->getJson('/api/tasks?q=clientes&environment=certification')->assertJsonCount(1, 'tasks');
        $this->getJson('/api/tasks?environment=production')->assertJsonCount(0, 'tasks');
        $this->deleteJson("/api/tasks/$id")->assertNoContent();
        Storage::disk('local')->assertMissing($file->path);
        $this->assertDatabaseMissing('tasks', ['id' => $id]);
        $this->assertDatabaseMissing('attachments', ['id' => $file->id]);
    }
    public function test_other_accounts_cannot_access_tasks_or_files(): void {
        Storage::fake('local');
        $owner = User::factory()->create();
        $task = Task::create(['user_id' => $owner->id, 'title' => 'Privada', 'checklist' => [['text' => 'Paso', 'done' => false]]]);
        Storage::disk('local')->put('attachments/private.txt', 'privado');
        $file = $task->attachments()->create(['name' => 'private.txt', 'path' => 'attachments/private.txt', 'size' => 7]);
        $this->actingAs(User::factory()->create());
        $this->getJson('/api/tasks')->assertJsonCount(0, 'tasks');
        $this->getJson('/api/tasks/'.$task->id)->assertNotFound();
        $this->putJson('/api/tasks/'.$task->id, $this->payload())->assertNotFound();
        $this->patchJson('/api/tasks/'.$task->id.'/status', ['status' => 'done'])->assertNotFound();
        $this->patchJson('/api/tasks/'.$task->id.'/checklist', ['index' => 0, 'done' => true])->assertNotFound();
        $this->deleteJson('/api/tasks/'.$task->id)->assertNotFound();
        $this->getJson('/api/attachments/'.$file->id)->assertNotFound();
        $this->deleteJson('/api/attachments/'.$file->id)->assertNotFound();
        Storage::disk('local')->assertExists($file->path);
    }
    public function test_invalid_states_and_executable_uploads_are_rejected(): void {
        Storage::fake('local'); $this->actingAs(User::factory()->create());
        $this->postJson('/api/tasks', $this->payload(['status' => 'inventado']))->assertUnprocessable();
        $this->post('/api/tasks', $this->payload(['files' => [UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;')]]), ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertDatabaseCount('tasks', 0);
    }
}
