<?php

declare(strict_types=1);

namespace App\Http\Controllers\Knowledge;

use App\Http\Controllers\Controller;
use App\Models\Employee\Employee;
use App\Models\Knowledge\KnowledgeFeedback;
use App\Models\Knowledge\KnowledgeGap;
use App\Models\Knowledge\KnowledgeQuery;
use App\Services\Knowledge\BookStackConnector;
use App\Services\Knowledge\KnowledgeQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class KnowledgeChatController extends Controller
{
    public function __construct(
        private readonly KnowledgeQueryService $queryService,
        private readonly BookStackConnector    $bookstack,
    ) {}

    public function index(): View
    {
        $isPinSession = session('employee_id') && !Auth::check();
        $prefix       = $isPinSession ? 'mein.knowledge.' : 'employee.knowledge.';
        $employee     = $isPinSession ? Employee::find(session('employee_id')) : null;

        return view('knowledge.chat', [
            'layout'         => $isPinSession ? 'mein.layout' : 'employee.layout',
            'employee'       => $employee,
            'reactions'      => KnowledgeFeedback::REACTIONS,
            'quickQuestions' => $this->quickQuestions(),
            'askUrl'         => route($prefix . 'ask'),
            'feedbackUrl'    => route($prefix . 'feedback', ':id'),
            'gapUrl'         => route($prefix . 'gap'),
            'draftUrl'       => route($prefix . 'draft'),
        ]);
    }

    public function ask(Request $request): JsonResponse
    {
        $request->validate([
            'question' => ['required', 'string', 'min:3', 'max:500'],
            'filter'   => ['nullable', 'string', 'in:alle,wiki,dokumente,preislisten,technik'],
        ]);

        if (!config('knowledge.enabled')) {
            return response()->json(['error' => 'Wissensassistent ist nicht aktiviert.'], 503);
        }

        $result = $this->queryService->ask(
            Auth::user(),
            $request->input('question'),
            $request->input('filter'),
        );

        return response()->json($result);
    }

    public function feedback(Request $request, KnowledgeQuery $query): JsonResponse
    {
        $request->validate([
            'reaction' => ['required', 'string', 'in:' . implode(',', array_keys(KnowledgeFeedback::REACTIONS))],
            'comment'  => ['nullable', 'string', 'max:500'],
        ]);

        KnowledgeFeedback::updateOrCreate(
            [
                'query_id' => $query->id,
                'user_id'  => Auth::id(),
                'reaction' => $request->input('reaction'),
            ],
            ['comment' => $request->input('comment')],
        );

        return response()->json(['ok' => true]);
    }

    public function reportGap(Request $request): JsonResponse
    {
        $request->validate([
            'question' => ['required', 'string', 'min:3', 'max:500'],
            'comment'  => ['nullable', 'string', 'max:1000'],
            'query_id' => ['nullable', 'integer', 'exists:knowledge_queries,id'],
        ]);

        $gap = KnowledgeGap::create([
            'tenant_id' => config('knowledge.tenant_id'),
            'user_id'   => Auth::id(),
            'query_id'  => $request->input('query_id'),
            'question'  => $request->input('question'),
            'comment'   => $request->input('comment'),
            'status'    => KnowledgeGap::STATUS_OPEN,
        ]);

        return response()->json(['ok' => true, 'gap_id' => $gap->id]);
    }

    public function createDraft(Request $request): JsonResponse
    {
        $request->validate([
            'query_id' => ['required', 'integer', 'exists:knowledge_queries,id'],
            'comment'  => ['nullable', 'string', 'max:1000'],
        ]);

        if (!$this->bookstack->isConfigured()) {
            return response()->json(['error' => 'BookStack ist nicht konfiguriert.'], 503);
        }

        $query = KnowledgeQuery::with('sources')->findOrFail($request->input('query_id'));

        $draftContent = $this->buildDraftContent($query, $request->input('comment', ''));
        $draftTitle   = mb_substr($query->question, 0, 100);

        $page = $this->bookstack->createDraftPage($draftTitle, $draftContent);
        if (!$page) {
            return response()->json(['error' => 'Entwurf konnte nicht angelegt werden.'], 500);
        }

        \App\Models\Knowledge\KnowledgeDraft::create([
            'tenant_id'          => config('knowledge.tenant_id'),
            'query_id'           => $query->id,
            'created_by'         => Auth::id(),
            'title'              => $draftTitle,
            'content'            => $draftContent,
            'bookstack_page_id'  => (string) $page['id'],
            'status'             => \App\Models\Knowledge\KnowledgeDraft::STATUS_PUSHED,
        ]);

        return response()->json(['ok' => true, 'page_id' => $page['id']]);
    }

    private function buildDraftContent(KnowledgeQuery $query, string $comment): string
    {
        $sourcesText = '';
        foreach ($query->sources as $i => $src) {
            $sourcesText .= '- [' . ($i + 1) . '] ' . ($src->source_title ?? 'Unbekannte Quelle');
            if ($src->source_url) {
                $sourcesText .= ' — ' . $src->source_url;
            }
            $sourcesText .= "\n";
        }

        return <<<MARKDOWN
# {$query->question}

> KI-Entwurf – bitte prüfen

## Zweck

[Bitte ergänzen: Wofür ist diese Anleitung?]

## Wann anwenden?

[Bitte ergänzen: In welcher Situation gilt das?]

## Schritt-für-Schritt

{$query->answer}

## Fehler vermeiden

[Bitte ergänzen]

## Zuständig

[Bitte ergänzen]

## Quellen

{$sourcesText}

## Offene Fragen

{$comment}

## Letzte Prüfung
Noch nicht geprüft
MARKDOWN;
    }

    private function quickQuestions(): array
    {
        return [
            'Was mache ich, wenn Kunde nicht da ist?',
            'Wie läuft eine Veranstaltungsanfrage?',
            'Wie buche ich Vollgut-Rückgabe?',
            'Was tun bei EC-Gerät defekt?',
            'Wo finde ich LMIV-Daten?',
            'Wie prüfe ich eine Preisliste?',
        ];
    }
}
