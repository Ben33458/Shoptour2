<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

class KnowledgeAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config(['knowledge.enabled' => true]);
    }

    public function test_unauthenticated_user_cannot_access_chat(): void
    {
        $this->get(route('employee.knowledge.chat'))
            ->assertRedirect();
    }

    public function test_internal_user_can_access_chat(): void
    {
        $user = User::factory()->create(['role' => 'mitarbeiter']);

        $this->actingAs($user)
            ->get(route('employee.knowledge.chat'))
            ->assertOk();
    }

    public function test_admin_user_can_access_chat(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->get(route('employee.knowledge.chat'))
            ->assertOk();
    }

    public function test_unauthenticated_cannot_post_question(): void
    {
        $this->postJson(route('employee.knowledge.ask'), ['question' => 'Test?'])
            ->assertStatus(401);
    }

    public function test_unauthenticated_cannot_access_admin(): void
    {
        $this->get(route('admin.knowledge.index'))
            ->assertRedirect();
    }

    public function test_admin_can_access_knowledge_admin(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->get(route('admin.knowledge.index'))
            ->assertOk();
    }
}
