<?php

namespace Tests\Feature;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_tasks(): void
    {
        Task::create(['title' => 'Write API test']);

        $this->getJson('/api/tasks')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.title', 'Write API test')
            ->assertJsonPath('0.is_completed', false)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'title',
                    'is_completed',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    public function test_it_creates_a_task(): void
    {
        $this->postJson('/api/tasks', [
            'title' => 'Ship Laravel demo',
        ])
            ->assertCreated()
            ->assertJsonPath('title', 'Ship Laravel demo')
            ->assertJsonPath('is_completed', false);

        $this->assertDatabaseHas('tasks', [
            'title' => 'Ship Laravel demo',
            'is_completed' => false,
        ]);
    }

    public function test_it_requires_a_title_when_creating_a_task(): void
    {
        $this->postJson('/api/tasks', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);

        $this->assertDatabaseCount('tasks', 0);
    }
}
