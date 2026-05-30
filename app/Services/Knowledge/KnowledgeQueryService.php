<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Models\Knowledge\KnowledgeDocument;
use App\Models\Knowledge\KnowledgeQuery;
use App\Models\Knowledge\KnowledgeQuerySource;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KnowledgeQueryService
{
    private string $ollamaUrl;
    private string $chatModel;

    public function __construct(
        private readonly QdrantService    $qdrant,
        private readonly EmbeddingService $embedding,
        private readonly BookStackConnector $bookstack,
    ) {
        $this->ollamaUrl = rtrim((string) config('knowledge.ollama_url', 'http://ollama:11434'), '/');
        $this->chatModel = (string) config('knowledge.ollama_chat_model', 'qwen2.5:3b');
    }

    /**
     * Process a user question and return the answer with sources.
     */
    public function ask(?User $user, string $question, ?string $filter = null): array
    {
        $questionType    = $this->detectQuestionType($question);
        $tenantId        = (string) config('knowledge.tenant_id', 'kolabri');
        $hasCustomerData = $this->isCustomerDataQuestion($question);
        $hasInvoiceData  = $this->isInvoiceDataQuestion($question);

        // Embed the question
        $vector = $this->embedding->embed($question);
        if (!$vector) {
            return $this->noSourceAnswer($user, $question, $questionType, $tenantId, $filter, $hasCustomerData, $hasInvoiceData);
        }

        // Build Qdrant filter
        $qdrantFilter = $this->buildQdrantFilter($filter, $tenantId);

        // Primary: vector search — wide pool, dedup to 10 diverse sources
        $hits = $this->qdrant->search($vector, 100, $qdrantFilter);
        $hits = array_filter($hits, fn($h) => ($h['score'] ?? 0) >= 0.60);
        $hits = $this->deduplicateHits(array_values($hits), maxPerDoc: 1, limit: 10);

        // Hybrid boost: BookStack full-text search for pages the vector search may have missed
        $hits = $this->boostWithKeywordSearch($question, $vector, $qdrantFilter, $hits);

        // Build context from hits
        $context  = $this->buildContext($hits);
        $sources  = $this->extractSources($hits);

        // Generate answer via OpenAI
        [$answer, $usedInternal] = $this->generateAnswer(
            $question,
            $context,
            $questionType,
            $filter,
        );

        // Persist query
        $query = KnowledgeQuery::create([
            'tenant_id'             => $tenantId,
            'user_id'               => $user?->id,
            'question'              => $question,
            'answer'                => $answer,
            'question_type'         => $questionType,
            'used_internal_sources' => $usedInternal,
            'used_live_data'        => false,
            'has_customer_data'     => $hasCustomerData,
            'has_invoice_data'      => $hasInvoiceData,
            'filter'                => $filter,
            'source_count'          => count($sources),
        ]);

        // Persist sources
        foreach ($sources as $i => $source) {
            KnowledgeQuerySource::create(array_merge($source, ['query_id' => $query->id]));
        }

        // Audit log for sensitive queries
        if ($hasCustomerData || $hasInvoiceData) {
            Log::channel('daily')->info('KnowledgeSensitiveQuery', [
                'user_id'          => $user?->id,
                'query_id'         => $query->id,
                'has_customer'     => $hasCustomerData,
                'has_invoice'      => $hasInvoiceData,
                'question_excerpt' => mb_substr($question, 0, 100),
            ]);
        }

        return [
            'query_id'  => $query->id,
            'answer'    => $answer,
            'sources'   => $sources,
            'type'      => $questionType,
        ];
    }

    private function detectQuestionType(string $question): string
    {
        $dataKeywords = [
            'kostet', 'preis', 'bestand', 'lager', 'adresse', 'rechnung',
            'offene', 'mahnung', 'aktuell', 'gerade', 'wie viel', 'wieviel',
        ];

        $lower = mb_strtolower($question);
        foreach ($dataKeywords as $kw) {
            if (str_contains($lower, $kw)) {
                return KnowledgeQuery::TYPE_DATA;
            }
        }

        return KnowledgeQuery::TYPE_KNOWLEDGE;
    }

    private function buildQdrantFilter(?string $filter, string $tenantId): array
    {
        $must    = [['key' => 'tenant_id', 'match' => ['value' => $tenantId]]];
        $mustNot = [];

        // Exclude technical docs from non-technical queries (must_not to also match missing/null)
        if ($filter !== 'technik') {
            $mustNot[] = ['key' => 'is_technical', 'match' => ['value' => true]];
        }

        if ($filter && $filter !== 'alle') {
            $sourceTypeMap = [
                'wiki'        => 'bookstack',
                'dokumente'   => 'paperless',
                'preislisten' => 'price_list',
                'technik'     => 'bookstack',
            ];
            if (isset($sourceTypeMap[$filter])) {
                if ($filter === 'preislisten') {
                    $must[] = ['key' => 'is_price_list', 'match' => ['value' => true]];
                } else {
                    $must[] = ['key' => 'source_type', 'match' => ['value' => $sourceTypeMap[$filter]]];
                }
            }
        }

        $result = ['must' => $must];
        if (!empty($mustNot)) {
            $result['must_not'] = $mustNot;
        }
        return $result;
    }

    private function deduplicateHits(array $hits, int $maxPerDoc, int $limit): array
    {
        $seen   = [];
        $result = [];
        foreach ($hits as $hit) {
            // Group by title to handle duplicate pages with different IDs
            $key   = strtolower(trim($hit['payload']['title'] ?? $hit['payload']['source_document_id'] ?? (string) ($hit['id'] ?? '')));
            $count = $seen[$key] ?? 0;
            if ($count < $maxPerDoc) {
                $seen[$key] = $count + 1;
                $result[]   = $hit;
                if (count($result) >= $limit) {
                    break;
                }
            }
        }
        return $result;
    }

    /**
     * Boost results with BookStack full-text search + MySQL title fallback.
     * Finds pages whose content matches the query keywords but that ranked too low in vector search.
     * Prepends up to 3 keyword-matched pages not already in $hits.
     */
    private function boostWithKeywordSearch(string $question, array $vector, array $qdrantFilter, array $hits): array
    {
        try {
            $bsPages = $this->bookstack->searchPages($question, 5);
        } catch (\Throwable) {
            return $hits;
        }

        // Fallback: MySQL title search when BookStack finds nothing.
        // Handles German verb/noun form mismatches (e.g. "verräume" ≠ "Verräumung")
        // by also searching with crude stems (strip last 2 chars for inflected forms).
        if (empty($bsPages)) {
            $tenantId = (string) config('knowledge.tenant_id', 'kolabri');
            $words    = preg_split('/\W+/u', mb_strtolower($question), -1, PREG_SPLIT_NO_EMPTY);
            $keywords = array_values(array_filter($words, fn($w) => mb_strlen($w) >= 6));
            // Build search terms: each keyword + its crude stem (drop last 2 chars if >= 8 chars)
            $searchTerms = [];
            foreach (array_slice($keywords, 0, 3) as $kw) {
                $searchTerms[] = $kw;
                if (mb_strlen($kw) >= 8) {
                    $searchTerms[] = mb_substr($kw, 0, -2); // "verräume" → "verräum"
                }
            }
            if (!empty($searchTerms)) {
                $docs = KnowledgeDocument::where('tenant_id', $tenantId)
                    ->where('indexed', true)
                    ->where(function ($q) {
                        $q->where('is_technical', false)->orWhereNull('is_technical');
                    })
                    ->where(function ($q) use ($searchTerms) {
                        foreach ($searchTerms as $term) {
                            $q->orWhere('title', 'like', "%{$term}%");
                        }
                    })
                    ->limit(3)
                    ->get(['external_id', 'title']);
                foreach ($docs as $doc) {
                    $bsPages[] = ['id' => (string) $doc->external_id, 'title' => $doc->title];
                }
            }
        }

        if (empty($bsPages)) {
            return $hits;
        }

        // Build set of already-included titles (lowercased)
        $existingTitles = [];
        foreach ($hits as $h) {
            $existingTitles[strtolower(trim($h['payload']['title'] ?? ''))] = true;
        }

        $boostHits = [];
        foreach ($bsPages as $page) {
            if (isset($existingTitles[strtolower(trim($page['title']))])) {
                continue; // already in results
            }

            // Fetch the best Qdrant chunk for this specific page
            $pageFilter = [
                'must'     => array_merge(
                    $qdrantFilter['must'] ?? [],
                    [['key' => 'source_document_id', 'match' => ['value' => $page['id']]]]
                ),
                'must_not' => $qdrantFilter['must_not'] ?? [],
            ];
            $pageHits = $this->qdrant->search($vector, 1, $pageFilter);
            if (!empty($pageHits)) {
                $boostHits[] = $pageHits[0];
                $existingTitles[strtolower(trim($page['title']))] = true;
                if (count($boostHits) >= 3) {
                    break;
                }
            }
        }

        if (empty($boostHits)) {
            return $hits;
        }

        // Prepend keyword-boosted hits, keep total at 10
        return array_slice(array_merge($boostHits, $hits), 0, 10);
    }

    private function buildContext(array $hits): string
    {
        if (empty($hits)) {
            return '';
        }

        $parts = [];
        foreach ($hits as $i => $hit) {
            $payload = $hit['payload'] ?? [];
            $title   = $payload['title'] ?? 'Unbekannte Quelle';
            $text    = $payload['chunk_text'] ?? '';

            $meta = [];
            if (!empty($payload['document_date'])) {
                $meta[] = "Datum: {$payload['document_date']}";
            }
            if (!empty($payload['valid_from'])) {
                $meta[] = "Gültig ab: {$payload['valid_from']}";
            }
            if (!empty($payload['valid_until'])) {
                $meta[] = "Gültig bis: {$payload['valid_until']}";
            }
            if (!empty($payload['source_type'])) {
                $meta[] = "Typ: {$payload['source_type']}";
            }

            $header  = "[Quelle " . ($i + 1) . "] {$title}";
            if ($meta) {
                $header .= " (" . implode(" | ", $meta) . ")";
            }

            $parts[] = "{$header}\n{$text}";
        }

        return implode("\n\n---\n\n", $parts);
    }

    private function extractSources(array $hits): array
    {
        $sources = [];
        foreach ($hits as $i => $hit) {
            $payload = $hit['payload'] ?? [];
            $sources[] = [
                'source_type'  => $payload['source_type'] ?? 'unknown',
                'source_id'    => $payload['source_document_id'] ?? null,
                'source_title' => $payload['title'] ?? null,
                'source_url'   => $payload['url'] ?? null,
                'chunk_id'     => $hit['id'] ?? null,
                'score'        => round((float) ($hit['score'] ?? 0), 4),
                'snippet'      => mb_substr($payload['chunk_text'] ?? '', 0, 300),
                'metadata'     => [
                    'is_price_list' => $payload['is_price_list'] ?? false,
                    'is_lmiv'       => $payload['is_lmiv'] ?? false,
                    'document_date' => $payload['document_date'] ?? null,
                    'valid_from'    => $payload['valid_from'] ?? null,
                    'valid_until'   => $payload['valid_until'] ?? null,
                ],
            ];
        }
        return $sources;
    }

    private function generateAnswer(
        string $question,
        string $context,
        string $questionType,
        ?string $filter,
    ): array {
        $systemPrompt = $this->buildSystemPrompt($questionType, $filter);
        $userMessage  = empty($context)
            ? "Frage: {$question}\n\nEs wurden keine internen Quellen gefunden."
            : "Frage: {$question}\n\nInterne Quellen:\n\n{$context}";

        try {
            $response = Http::timeout(240)
                ->post("{$this->ollamaUrl}/v1/chat/completions", [
                    'model'       => $this->chatModel,
                    'max_tokens'  => 600,
                    'temperature' => 0.2,
                    'messages'    => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user',   'content' => $userMessage],
                    ],
                ]);

            if ($response->successful()) {
                $answer = $response->json('choices.0.message.content', '');
                return [$answer, !empty($context)];
            }

            Log::error('Knowledge: Ollama chat error ' . $response->status() . ': ' . $response->body());
        } catch (\Throwable $e) {
            Log::error('Knowledge: Ollama chat failed: ' . $e->getMessage());
        }

        return [$this->fallbackAnswer($context), !empty($context)];
    }

    private function buildSystemPrompt(string $questionType, ?string $filter): string
    {
        $extra = match ($questionType) {
            KnowledgeQuery::TYPE_DATA => "\n- Preise/Bestände: Immer Dokumentdatum und Gültigkeit der Quelle nennen. Darauf hinweisen, dass aktuelle Preise in ShopTour2/JTL verifiziert werden müssen.",
            'LMIV'                    => "\n- LMIV/Allergene: Nur mit explizitem Quellennachweis antworten, niemals Inhaltsstoffe oder Allergene erfinden.",
            'PROCESS'                 => "\n- Prozesse/Abläufe: Schritt-für-Schritt-Anleitung bevorzugen, Schritte nummerieren.",
            default                   => '',
        };

        return <<<PROMPT
Du bist der interne Wissensassistent von Kolabri Getränke. WICHTIG: Antworte AUSSCHLIESSLICH AUF DEUTSCH. Kein Englisch.

Du beantwortest Fragen von Mitarbeitern ausschließlich auf Basis der bereitgestellten internen Quellen.

Regeln:
- ANTWORTE AUF DEUTSCH — jede Antwort muss auf Deutsch sein.
- Kurz und praktisch antworten.
- Nutze Schritt-für-Schritt-Listen wenn eine Handlungsanweisung gefragt ist.
- Referenziere Quellen mit [Quelle 1], [Quelle 2] usw. am Ende relevanter Aussagen.
- Wenn die Quellen die Frage nicht beantworten können: Schreibe "Ich finde dazu keine freigegebene interne Quelle." — erfinde keine Informationen.
- Keine Lohn-, Bank-, Steuer- oder Privatdaten ausgeben.{$extra}
PROMPT;
    }

    private function fallbackAnswer(string $context): string
    {
        return 'Der Wissensassistent konnte aktuell keine Antwort generieren (Zeitüberschreitung). Bitte versuche es nochmals oder wende dich an deinen Vorgesetzten.';
    }

    private function noSourceAnswer(?User $user, string $question, string $questionType, string $tenantId, ?string $filter, bool $hasCustomerData = false, bool $hasInvoiceData = false): array
    {
        $answer = 'Ich finde dazu keine freigegebene interne Quelle. Allgemein gilt: Bitte wende dich an deine Vorgesetzte/deinen Vorgesetzten.';

        $query = KnowledgeQuery::create([
            'tenant_id'             => $tenantId,
            'user_id'               => $user?->id,
            'question'              => $question,
            'answer'                => $answer,
            'question_type'         => $questionType,
            'used_internal_sources' => false,
            'used_live_data'        => false,
            'has_customer_data'     => $hasCustomerData,
            'has_invoice_data'      => $hasInvoiceData,
            'filter'                => $filter,
            'source_count'          => 0,
        ]);

        if ($hasCustomerData || $hasInvoiceData) {
            Log::channel('daily')->info('KnowledgeSensitiveQuery', [
                'user_id'          => $user?->id,
                'query_id'         => $query->id,
                'has_customer'     => $hasCustomerData,
                'has_invoice'      => $hasInvoiceData,
                'question_excerpt' => mb_substr($question, 0, 100),
            ]);
        }

        return [
            'query_id' => $query->id,
            'answer'   => $answer,
            'sources'  => [],
            'type'     => $questionType,
        ];
    }

    private function isCustomerDataQuestion(string $question): bool
    {
        $keywords = ['adresse', 'kunde', 'kunden', 'kontakt', 'telefon', 'email', 'kundennummer'];
        $lower = mb_strtolower($question);
        foreach ($keywords as $kw) {
            if (str_contains($lower, $kw)) {
                return true;
            }
        }
        return false;
    }

    private function isInvoiceDataQuestion(string $question): bool
    {
        $keywords = ['rechnung', 'rechnungen', 'mahnung', 'offene', 'zahlungsrückstand', 'schulden'];
        $lower = mb_strtolower($question);
        foreach ($keywords as $kw) {
            if (str_contains($lower, $kw)) {
                return true;
            }
        }
        return false;
    }
}
