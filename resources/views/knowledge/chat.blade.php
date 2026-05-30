@extends($layout)

@section('title', 'Wissensassistent')

@push('head')
<style>
/* ── Wissensassistent Chat — mobile-first ───────────────────────────────── */
.ka-wrap {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 56px - 56px);
    max-width: 800px;
    margin: 0 auto;
    gap: 0;
}
.ka-header {
    padding: 12px 0 8px;
}
.ka-header h1 {
    font-size: 18px;
    font-weight: 700;
    margin: 0 0 8px;
    color: var(--c-text);
}
.ka-filters {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin-bottom: 8px;
}
.ka-filter-btn {
    padding: 4px 12px;
    border-radius: 20px;
    border: 1px solid var(--c-border);
    background: var(--c-surface);
    color: var(--c-muted);
    font-size: 13px;
    cursor: pointer;
    transition: all .15s;
}
.ka-filter-btn.active,
.ka-filter-btn:hover {
    background: var(--c-primary);
    color: #fff;
    border-color: var(--c-primary);
}

/* Messages area */
.ka-messages {
    flex: 1;
    overflow-y: auto;
    padding: 12px 0;
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.ka-quick-questions {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 8px;
}
.ka-quick-btn {
    padding: 10px 14px;
    border-radius: 8px;
    border: 1px solid var(--c-border);
    background: var(--c-surface);
    color: var(--c-text);
    font-size: 14px;
    text-align: left;
    cursor: pointer;
    transition: border-color .15s;
}
.ka-quick-btn:hover { border-color: var(--c-primary); color: var(--c-primary); }

/* Message bubbles */
.ka-msg { display: flex; flex-direction: column; gap: 8px; }
.ka-msg-user {
    align-self: flex-end;
    background: var(--c-primary);
    color: #fff;
    padding: 10px 14px;
    border-radius: 12px 12px 2px 12px;
    max-width: 75%;
    font-size: 14px;
}
.ka-msg-bot {
    align-self: flex-start;
    max-width: 100%;
}
.ka-answer {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 12px;
    padding: 14px 16px;
    font-size: 14px;
    line-height: 1.6;
    white-space: pre-wrap;
}
.ka-source-tag {
    display: inline-block;
    background: var(--c-primary);
    color: #fff;
    border-radius: 4px;
    padding: 1px 5px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    margin: 0 1px;
}

/* Sources list */
.ka-sources {
    margin-top: 8px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.ka-source-card {
    border: 1px solid var(--c-border);
    border-radius: 8px;
    overflow: hidden;
    font-size: 13px;
}
.ka-source-card-head {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: var(--c-bg);
    cursor: pointer;
}
.ka-source-card-head:hover { background: var(--c-border); }
.ka-source-badge {
    font-size: 11px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 10px;
    background: var(--c-primary);
    color: #fff;
}
.ka-source-title { font-weight: 600; flex: 1; }
.ka-source-meta { color: var(--c-muted); font-size: 12px; }
.ka-source-snippet {
    padding: 8px 12px;
    color: var(--c-muted);
    border-top: 1px solid var(--c-border);
    display: none;
    font-size: 12px;
    line-height: 1.5;
}
.ka-source-snippet.open { display: block; }
.ka-source-link {
    display: inline-block;
    margin-top: 6px;
    color: var(--c-primary);
    font-size: 12px;
    text-decoration: none;
}
.ka-source-link:hover { text-decoration: underline; }

/* Price list / LMIV warnings */
.ka-price-warning, .ka-lmiv-warning {
    margin-top: 8px;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 12px;
    display: flex;
    gap: 8px;
    align-items: flex-start;
}
.ka-price-warning { background: #fff3cd; border: 1px solid #ffc107; color: #856404; }
.ka-lmiv-warning  { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }

/* Reactions */
.ka-reactions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 8px;
}
.ka-reaction-btn {
    font-size: 13px;
    padding: 4px 10px;
    border-radius: 20px;
    border: 1px solid var(--c-border);
    background: var(--c-surface);
    cursor: pointer;
    transition: all .15s;
}
.ka-reaction-btn:hover { border-color: var(--c-primary); background: var(--c-bg); }
.ka-reaction-btn.active { background: var(--c-primary); color: #fff; border-color: var(--c-primary); }
.ka-gap-btn {
    font-size: 12px;
    color: var(--c-muted);
    background: none;
    border: 1px dashed var(--c-border);
    border-radius: 6px;
    padding: 4px 10px;
    cursor: pointer;
    margin-top: 4px;
}
.ka-gap-btn:hover { border-color: var(--c-primary); color: var(--c-primary); }
.ka-draft-btn {
    font-size: 12px;
    color: var(--c-primary);
    background: none;
    border: 1px solid var(--c-primary);
    border-radius: 6px;
    padding: 4px 10px;
    cursor: pointer;
    margin-top: 4px;
}
.ka-draft-btn:hover { background: var(--c-primary); color: #fff; }

/* Input area */
.ka-input-area {
    padding: 12px 0;
    border-top: 1px solid var(--c-border);
    background: var(--c-bg);
}
.ka-input-row {
    display: flex;
    gap: 8px;
    align-items: flex-end;
}
.ka-input {
    flex: 1;
    border: 1px solid var(--c-border);
    border-radius: 10px;
    padding: 10px 14px;
    font-size: 15px;
    resize: none;
    min-height: 44px;
    max-height: 120px;
    background: var(--c-surface);
    color: var(--c-text);
    font-family: inherit;
}
.ka-input:focus { outline: none; border-color: var(--c-primary); }
.ka-send-btn {
    padding: 10px 18px;
    background: var(--c-primary);
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    min-height: 44px;
}
.ka-send-btn:disabled { opacity: .5; cursor: default; }

/* Typing indicator */
.ka-typing {
    display: none;
    align-items: center;
    gap: 4px;
    padding: 8px 0;
    color: var(--c-muted);
    font-size: 13px;
}
.ka-typing.show { display: flex; }
.ka-dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    background: var(--c-muted);
    animation: ka-blink 1.2s infinite;
}
.ka-dot:nth-child(2) { animation-delay: .2s; }
.ka-dot:nth-child(3) { animation-delay: .4s; }
@keyframes ka-blink {
    0%, 80%, 100% { opacity: .2; }
    40%            { opacity: 1; }
}

@media (max-width: 600px) {
    .ka-wrap { height: calc(100vh - 56px - 40px); }
    .ka-msg-user { max-width: 90%; }
}
</style>
@endpush

@section('content')
<div class="ka-wrap">

    {{-- Header + Filter --}}
    <div class="ka-header">
        <h1>🧠 Wissensassistent</h1>
        <div class="ka-filters">
            @foreach(['alle' => 'Alle', 'wiki' => 'Wiki', 'dokumente' => 'Dokumente', 'preislisten' => 'Preislisten', 'technik' => 'Technik'] as $val => $label)
            <button class="ka-filter-btn {{ $val === 'alle' ? 'active' : '' }}"
                    data-filter="{{ $val }}"
                    onclick="setFilter('{{ $val }}', this)">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    {{-- Messages --}}
    <div class="ka-messages" id="ka-messages">

        {{-- Welcome / quick questions --}}
        <div class="ka-msg" id="ka-welcome">
            <div class="ka-answer">
                Hallo! Ich bin dein interner Wissensassistent. Stell mir eine Frage oder wähle eine Schnellfrage:
            </div>
            <div class="ka-quick-questions">
                @foreach($quickQuestions as $q)
                <button class="ka-quick-btn" onclick="sendQuestion({{ json_encode($q) }})">{{ $q }}</button>
                @endforeach
            </div>
        </div>

    </div>

    {{-- Typing indicator --}}
    <div class="ka-typing" id="ka-typing">
        <span class="ka-dot"></span>
        <span class="ka-dot"></span>
        <span class="ka-dot"></span>
        <span style="margin-left:4px">Wissensassistent denkt nach…</span>
    </div>

    {{-- Input --}}
    <div class="ka-input-area">
        <div class="ka-input-row">
            <textarea
                id="ka-input"
                class="ka-input"
                placeholder="Frage stellen…"
                rows="1"
                onkeydown="handleKey(event)"
                oninput="autoGrow(this)"
            ></textarea>
            <button class="ka-send-btn" id="ka-send" onclick="sendFromInput()">Senden</button>
        </div>
    </div>

</div>

{{-- Gap modal --}}
<div id="ka-gap-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center">
    <div style="background:var(--c-surface);border-radius:12px;padding:24px;max-width:480px;width:90%;box-shadow:0 8px 32px rgba(0,0,0,.2)">
        <h3 style="margin:0 0 12px;font-size:16px">Wissensseite fehlt</h3>
        <textarea id="ka-gap-comment" placeholder="Was fehlt? Was wäre hilfreich zu wissen?"
            style="width:100%;border:1px solid var(--c-border);border-radius:8px;padding:10px;font-size:14px;min-height:80px;box-sizing:border-box;font-family:inherit;resize:vertical"></textarea>
        <div style="display:flex;gap:8px;margin-top:12px;justify-content:flex-end">
            <button onclick="closeGapModal()" style="padding:8px 16px;border:1px solid var(--c-border);border-radius:8px;background:none;cursor:pointer">Abbrechen</button>
            <button onclick="submitGap()" style="padding:8px 16px;background:var(--c-primary);color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:600">Melden</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const csrfToken   = document.querySelector('meta[name="csrf-token"]').content;
const askUrl      = '{{ $askUrl }}';
const feedbackUrl = '{{ $feedbackUrl }}';
const gapUrl      = '{{ $gapUrl }}';
const draftUrl    = '{{ $draftUrl }}';
const reactions   = @json($reactions);

let currentFilter = 'alle';
let currentQueryId = null;
let gapQueryId = null;

function setFilter(val, btn) {
    currentFilter = val;
    document.querySelectorAll('.ka-filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

function handleKey(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendFromInput();
    }
}

function autoGrow(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 120) + 'px';
}

function sendFromInput() {
    const input = document.getElementById('ka-input');
    const q = input.value.trim();
    if (!q) return;
    input.value = '';
    input.style.height = 'auto';
    sendQuestion(q);
}

function sendQuestion(question) {
    document.getElementById('ka-welcome')?.remove();

    // Add user bubble
    appendUserBubble(question);

    // Show typing
    document.getElementById('ka-typing').classList.add('show');
    document.getElementById('ka-send').disabled = true;
    scrollBottom();

    fetch(askUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
        },
        body: JSON.stringify({ question, filter: currentFilter }),
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('ka-typing').classList.remove('show');
        document.getElementById('ka-send').disabled = false;

        if (data.error) {
            appendErrorBubble(data.error);
            return;
        }

        currentQueryId = data.query_id;
        appendAnswerBubble(data);
        scrollBottom();
    })
    .catch(() => {
        document.getElementById('ka-typing').classList.remove('show');
        document.getElementById('ka-send').disabled = false;
        appendErrorBubble('Verbindungsfehler. Bitte erneut versuchen.');
    });
}

function appendUserBubble(question) {
    const msg = document.createElement('div');
    msg.className = 'ka-msg';
    msg.innerHTML = `<div class="ka-msg-user">${escHtml(question)}</div>`;
    document.getElementById('ka-messages').appendChild(msg);
}

function appendAnswerBubble(data) {
    const msg = document.createElement('div');
    msg.className = 'ka-msg ka-msg-bot';
    msg.dataset.queryId = data.query_id;

    const answerHtml = formatAnswer(data.answer, data.sources || []);
    const sourcesHtml = renderSources(data.sources || []);
    const warningsHtml = renderWarnings(data.sources || []);
    const reactionsHtml = renderReactions(data.query_id);
    const actionsHtml = renderActions(data.query_id, data.answer);

    msg.innerHTML = `
        <div class="ka-answer">${answerHtml}</div>
        ${warningsHtml}
        ${sourcesHtml}
        ${reactionsHtml}
        ${actionsHtml}
    `;
    document.getElementById('ka-messages').appendChild(msg);
}

function appendErrorBubble(text) {
    const msg = document.createElement('div');
    msg.className = 'ka-msg ka-msg-bot';
    msg.innerHTML = `<div class="ka-answer" style="border-color:var(--c-danger);color:var(--c-danger)">⚠️ ${escHtml(text)}</div>`;
    document.getElementById('ka-messages').appendChild(msg);
}

function formatAnswer(text, sources) {
    // Replace [Quelle N] with clickable badges
    return escHtml(text).replace(/\[Quelle (\d+)\]/g, (_, n) => {
        const src = sources[parseInt(n) - 1];
        return `<a class="ka-source-tag" href="#" onclick="toggleSource(${parseInt(n) - 1}, event)">[Quelle ${n}]</a>`;
    });
}

function renderSources(sources) {
    if (!sources.length) return '';

    const items = sources.map((src, i) => {
        const badge = sourceTypeBadge(src.source_type);
        const meta  = src.metadata || {};
        const metaText = [
            meta.document_date ? `Datum: ${meta.document_date}` : null,
            meta.valid_from    ? `gültig ab: ${meta.valid_from}` : null,
            meta.valid_until   ? `bis: ${meta.valid_until}` : null,
        ].filter(Boolean).join(' · ');

        return `
        <div class="ka-source-card" id="ka-src-${i}">
            <div class="ka-source-card-head" onclick="toggleSource(${i}, event)">
                <span class="ka-source-badge">${badge}</span>
                <span class="ka-source-title">${escHtml(src.source_title || 'Quelle ' + (i+1))}</span>
                ${metaText ? `<span class="ka-source-meta">${escHtml(metaText)}</span>` : ''}
                <span style="color:var(--c-muted);font-size:11px">${(src.score * 100).toFixed(0)}%</span>
            </div>
            <div class="ka-source-snippet" id="ka-snip-${i}">
                ${escHtml(src.snippet || '')}
                ${src.source_url ? `<a class="ka-source-link" href="${escHtml(src.source_url)}" target="_blank">Vollständige Quelle anzeigen →</a>` : ''}
            </div>
        </div>`;
    }).join('');

    return `<div class="ka-sources">${items}</div>`;
}

function renderWarnings(sources) {
    const priceWarning = sources.some(s => s.metadata?.is_price_list);
    const lmivWarning  = sources.some(s => s.metadata?.is_lmiv);
    let html = '';

    if (priceWarning) {
        html += `<div class="ka-price-warning">⚠️ <span>Diese Antwort enthält Informationen aus einer Preisliste.
            Verbindliche aktuelle Preise bitte in ShopTour2/JTL prüfen.
            Datum und Gültigkeit stehen in der Quellenangabe.</span></div>`;
    }
    if (lmivWarning) {
        html += `<div class="ka-lmiv-warning">ℹ️ <span>LMIV-Angaben stammen aus freigegebenen internen Dokumenten.
            Bitte Quelle und Aktualität prüfen bevor diese Angaben weitergegeben werden.</span></div>`;
    }
    return html;
}

function renderReactions(queryId) {
    const btns = Object.entries(reactions).map(([key, label]) =>
        `<button class="ka-reaction-btn" data-reaction="${key}" onclick="sendReaction(${queryId}, '${key}', this)">${label}</button>`
    ).join('');
    return `<div class="ka-reactions">${btns}</div>`;
}

function renderActions(queryId) {
    return `
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:4px">
        <button class="ka-gap-btn" onclick="openGapModal(${queryId})">📭 Wissensseite fehlt</button>
        <button class="ka-draft-btn" onclick="createDraft(${queryId}, this)">✏️ Entwurf erzeugen</button>
    </div>`;
}

function toggleSource(i, e) {
    e.preventDefault();
    const snip = document.getElementById(`ka-snip-${i}`);
    if (snip) snip.classList.toggle('open');
}

function sendReaction(queryId, reaction, btn) {
    btn.classList.toggle('active');
    const url = feedbackUrl.replace(':id', queryId);
    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ reaction }),
    });
}

function openGapModal(queryId) {
    gapQueryId = queryId;
    document.getElementById('ka-gap-modal').style.display = 'flex';
    document.getElementById('ka-gap-comment').value = '';
    document.getElementById('ka-gap-comment').focus();
}

function closeGapModal() {
    document.getElementById('ka-gap-modal').style.display = 'none';
}

function submitGap() {
    const ta = document.getElementById('ka-gap-comment');
    const comment = ta.value.trim();
    if (!comment) {
        ta.style.borderColor = 'var(--c-danger, #dc3545)';
        ta.focus();
        return;
    }
    ta.style.borderColor = '';

    const question = document.querySelector(`[data-query-id="${gapQueryId}"]`)
        ?.previousElementSibling
        ?.querySelector('.ka-msg-user')
        ?.textContent || '';

    fetch(gapUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ question, comment, query_id: gapQueryId }),
    })
    .then(r => r.json())
    .then(() => {
        const modal = document.getElementById('ka-gap-modal').querySelector('div');
        modal.innerHTML = '<p style="margin:0;font-size:15px;text-align:center;padding:16px 0">✅ Wissenslücke wurde gemeldet. Danke!</p>';
        setTimeout(closeGapModal, 1500);
    });
}

function createDraft(queryId, btn) {
    btn.disabled = true;
    btn.textContent = '⏳ Wird erstellt…';

    fetch(draftUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ query_id: queryId }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            btn.textContent = '✓ Entwurf angelegt';
            btn.style.color = 'var(--c-success, #198754)';
            btn.style.borderColor = 'var(--c-success, #198754)';
        } else {
            btn.textContent = '✗ ' + (data.error || 'Fehler');
            btn.style.color = 'var(--c-danger, #dc3545)';
            btn.style.borderColor = 'var(--c-danger, #dc3545)';
            setTimeout(() => { btn.textContent = '✏️ Entwurf erzeugen'; btn.style.color = ''; btn.style.borderColor = ''; btn.disabled = false; }, 3000);
        }
    })
    .catch(() => {
        btn.textContent = '✗ Verbindungsfehler';
        btn.style.color = 'var(--c-danger, #dc3545)';
        setTimeout(() => { btn.textContent = '✏️ Entwurf erzeugen'; btn.style.color = ''; btn.disabled = false; }, 3000);
    });
}

function sourceTypeBadge(type) {
    const map = {
        bookstack:  'Wiki',
        paperless:  'Dok.',
        price_list: 'Preis',
        lmiv:       'LMIV',
        shoptour2:  'Live',
    };
    return map[type] || type;
}

function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function scrollBottom() {
    const el = document.getElementById('ka-messages');
    el.scrollTop = el.scrollHeight;
}

// Close gap modal on backdrop click
document.getElementById('ka-gap-modal').addEventListener('click', function(e) {
    if (e.target === this) closeGapModal();
});
</script>
@endpush
