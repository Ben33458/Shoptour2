<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Models\Knowledge\KnowledgeFeedback;
use App\Models\Knowledge\KnowledgeGap;
use App\Models\Knowledge\KnowledgeQuery;
use App\Models\User;
use App\Services\Knowledge\KnowledgeQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KnowledgeLoggingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config(['knowledge.enabled' => true]);

        // Mock HTTP calls so tests don't need real Ollama/Qdrant
        Http::fake([
            '*/api/embed'                => Http::response(['embeddings' => [array_fill(0, 768, 0.1)]]),
            '*/v1/chat/completions'      => Http::response(['choices' => [['message' => ['content' => 'Testantwort']]]]),
            '*/points/search'            => Http::response(['result' => []]),
            '*/collections/*'            => Http::response(['status' => 'ok', 'result' => ['status' => 'green']]),
        ]);
    }

    public function test_normal_question_is_logged(): void
    {
        $user = User::factory()->create(['role' => 'mitarbeiter']);

        $this->actingAs($user)->postJson(route('employee.knowledge.ask'), [
            'question' => 'Wie läuft Vollgut-Rückgabe?',
        ])->assertOk();

        $this->assertDatabaseHas('knowledge_queries', [
            'user_id'       => $user->id,
            'question_type' => 'knowledge',
        ]);
    }

    public function test_customer_data_question_is_flagged(): void
    {
        $user = User::factory()->create(['role' => 'mitarbeiter']);

        $this->actingAs($user)->postJson(route('employee.knowledge.ask'), [
            'question' => 'Welche Adresse hat Kunde Müller?',
        ])->assertOk();

        $this->assertDatabaseHas('knowledge_queries', [
            'user_id'           => $user->id,
            'has_customer_data' => true,
        ]);
    }

    public function test_invoice_question_is_flagged(): void
    {
        $user = User::factory()->create(['role' => 'mitarbeiter']);

        $this->actingAs($user)->postJson(route('employee.knowledge.ask'), [
            'question' => 'Hat Kunde Schulz offene Rechnungen?',
        ])->assertOk();

        $this->assertDatabaseHas('knowledge_queries', [
            'has_invoice_data' => true,
        ]);
    }

    public function test_feedback_is_saved(): void
    {
        $user  = User::factory()->create(['role' => 'mitarbeiter']);
        $query = KnowledgeQuery::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->postJson(route('employee.knowledge.feedback', $query->id), [
            'reaction' => 'helpful',
        ])->assertOk();

        $this->assertDatabaseHas('knowledge_feedback', [
            'query_id' => $query->id,
            'user_id'  => $user->id,
            'reaction' => 'helpful',
        ]);
    }

    public function test_knowledge_gap_is_saved(): void
    {
        $user = User::factory()->create(['role' => 'mitarbeiter']);

        $this->actingAs($user)->postJson(route('employee.knowledge.gap'), [
            'question' => 'Wie gehe ich bei Stromausfall vor?',
            'comment'  => 'Wir brauchen eine Notfallanleitung.',
        ])->assertOk();

        $this->assertDatabaseHas('knowledge_gaps', [
            'user_id'  => $user->id,
            'status'   => 'open',
        ]);
    }
}
