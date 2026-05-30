<?php

declare(strict_types=1);

namespace App\Http\Controllers\Knowledge;

use App\Http\Controllers\Controller;
use App\Models\Knowledge\KnowledgeDocument;
use App\Models\Knowledge\KnowledgeFeedback;
use App\Models\Knowledge\KnowledgeGap;
use App\Models\Knowledge\KnowledgeQuery;
use App\Models\Knowledge\KnowledgeSource;
use App\Models\Knowledge\KnowledgeSyncRun;
use App\Services\Knowledge\BookStackConnector;
use App\Services\Knowledge\EmbeddingService;
use App\Services\Knowledge\IndexingService;
use App\Services\Knowledge\PaperlessConnector;
use App\Services\Knowledge\QdrantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class KnowledgeAdminController extends Controller
{
    public function __construct(
        private readonly QdrantService      $qdrant,
        private readonly EmbeddingService   $embedding,
        private readonly PaperlessConnector $paperless,
        private readonly BookStackConnector $bookstack,
        private readonly IndexingService    $indexing,
    ) {}

    public function index(): View
    {
        $lastRuns = KnowledgeSyncRun::orderByDesc('started_at')->limit(20)->get();
        $sources  = KnowledgeSource::withCount('documents')->get();

        return view('knowledge.admin.index', [
            'lastRuns'       => $lastRuns,
            'sources'        => $sources,
            'totalDocs'      => KnowledgeDocument::count(),
            'indexedDocs'    => KnowledgeDocument::where('indexed', true)->count(),
            'totalChunks'    => \App\Models\Knowledge\KnowledgeChunk::count(),
            'qdrantPoints'   => $this->qdrant->count(),
        ]);
    }

    public function gaps(): View
    {
        $gaps = KnowledgeGap::with('user')
            ->orderByDesc('created_at')
            ->paginate(30);

        $negativeFeedback = KnowledgeFeedback::with(['query', 'user'])
            ->whereIn('reaction', ['wrong', 'outdated', 'critical'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $topQuestions = KnowledgeQuery::selectRaw('question, count(*) as cnt')
            ->where('source_count', 0)
            ->groupBy('question')
            ->orderByDesc('cnt')
            ->limit(20)
            ->get();

        return view('knowledge.admin.gaps', [
            'gaps'             => $gaps,
            'negativeFeedback' => $negativeFeedback,
            'topQuestions'     => $topQuestions,
        ]);
    }

    public function sync(Request $request): RedirectResponse
    {
        $source = $request->input('source', 'all');
        Artisan::queue("knowledge:sync --source={$source}");
        return back()->with('success', "Sync für '{$source}' gestartet.");
    }

    public function reindex(): RedirectResponse
    {
        Artisan::queue('knowledge:reindex');
        return back()->with('success', 'Vollständiger Reindex gestartet.');
    }

    public function removeSource(KnowledgeDocument $document): RedirectResponse
    {
        $this->indexing->removeDocumentFromQdrant($document);
        $document->delete();
        return back()->with('success', "Dokument '{$document->title}' aus Index entfernt.");
    }

    public function testConnections(): JsonResponse
    {
        return response()->json([
            'qdrant'    => $this->qdrant->testConnection(),
            'openai'    => config('knowledge.openai_api_key') ? $this->embedding->testConnection() : 'not_configured',
            'paperless' => count($this->paperless->fetchAllTags()) > 0,
            'bookstack' => $this->bookstack->isConfigured() ? $this->bookstack->testConnection() : 'not_configured',
        ]);
    }

    public function resolveGap(Request $request, KnowledgeGap $gap): RedirectResponse
    {
        $gap->update([
            'status'      => $request->input('status', KnowledgeGap::STATUS_RESOLVED),
            'resolved_at' => now(),
        ]);
        return back()->with('success', 'Wissenslücke aktualisiert.');
    }
}
