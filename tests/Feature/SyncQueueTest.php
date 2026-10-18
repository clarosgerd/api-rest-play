<?php

namespace Tests\Feature;

use App\Jobs\RunSyncJob;
use App\Models\SyncRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SyncQueueTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_sync_endpoint_queues_a_job_instead_of_running_synchronously(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->admin())->postJson('/api/v1/sync', [
            'type' => 'all',
            'source' => 'football',
        ]);

        $response->assertStatus(202);
        $response->assertJsonStructure(['run_id', 'status', 'type', 'season', 'source']);
        $this->assertEquals('queued', $response->json('status'));

        $this->assertDatabaseHas('sync_runs', [
            'sync_run_id' => $response->json('run_id'),
            'source' => 'football',
            'command' => 'football:sync',
            'status' => 'queued',
        ]);

        Queue::assertPushed(RunSyncJob::class, function (RunSyncJob $job) use ($response) {
            return $job->syncRunId === $response->json('run_id');
        });
    }

    public function test_non_admin_cannot_trigger_sync(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/sync', ['type' => 'all'])->assertStatus(403);

        Queue::assertNotPushed(RunSyncJob::class);
    }

    public function test_run_status_endpoint_returns_queued_run(): void
    {
        $admin = $this->admin();
        $run = SyncRun::create([
            'source' => 'football',
            'command' => 'football:sync',
            'arguments' => ['--type' => 'all'],
            'status' => 'queued',
        ]);

        $response = $this->actingAs($admin)->getJson("/api/v1/sync/runs/{$run->sync_run_id}");

        $response->assertOk();
        $response->assertJson([
            'run_id' => $run->sync_run_id,
            'status' => 'queued',
            'success' => false,
            'message' => null,
        ]);
    }

    public function test_run_sync_job_marks_run_completed_on_success(): void
    {
        Artisan::shouldReceive('call')->once()->andReturn(0);
        Artisan::shouldReceive('output')->once()->andReturn('log de prueba');

        $run = SyncRun::create([
            'source' => 'football',
            'command' => 'football:sync',
            'arguments' => ['--type' => 'competitions'],
            'status' => 'queued',
        ]);

        (new RunSyncJob($run->sync_run_id))->handle();

        $run->refresh();
        $this->assertEquals('completed', $run->status);
        $this->assertEquals('log de prueba', $run->log);
        $this->assertNotNull($run->finished_at);
    }

    public function test_run_sync_job_marks_run_failed_on_exception(): void
    {
        Artisan::shouldReceive('call')->once()->andThrow(new \RuntimeException('API caída'));
        Artisan::shouldReceive('output')->once()->andReturn('log parcial');

        $run = SyncRun::create([
            'source' => 'football',
            'command' => 'football:sync',
            'arguments' => ['--type' => 'teams'],
            'status' => 'queued',
        ]);

        (new RunSyncJob($run->sync_run_id))->handle();

        $run->refresh();
        $this->assertEquals('failed', $run->status);
        $this->assertEquals('API caída', $run->error);
        $this->assertNotNull($run->finished_at);
    }
}
