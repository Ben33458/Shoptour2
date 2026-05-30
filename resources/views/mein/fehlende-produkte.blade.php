@extends('mein.layout')

@section('title', 'Fehlende Produkte')

@section('content')

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:8px;">
    <h1 style="font-size:1.3rem; font-weight:700; margin:0;">Fehlende Produkte melden</h1>
</div>

@if(session('success'))
<div class="alert alert-success" style="margin-bottom:1rem;">{{ session('success') }}</div>
@endif

@if($errors->any())
<div class="alert alert-danger" style="margin-bottom:1rem;">
    @foreach($errors->all() as $e) <div>{{ $e }}</div> @endforeach
</div>
@endif

<div class="mein-card" style="margin-bottom:1.5rem;">
    <div class="mein-card-title" style="margin-bottom:1rem;">Neues Produkt melden</div>
    <form method="POST" action="{{ route('mein.fehlende-produkte.store') }}" id="missing-form">
        @csrf
        <input type="hidden" name="product_id" id="product_id" value="{{ old('product_id') }}">
        <input type="hidden" name="artikelnummer" id="artikelnummer_hidden" value="{{ old('artikelnummer') }}">

        <div class="form-group" style="margin-bottom:14px; position:relative;">
            <label>Produkt suchen <small style="font-weight:400; color:var(--c-muted);">(oder direkt eingeben)</small></label>
            <input type="text" id="produkt-search" autocomplete="off"
                   placeholder="Artikelnummer oder Produktname…"
                   value="{{ old('produktname') }}"
                   style="width:100%;">
            <div id="autocomplete-list" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid var(--c-border); border-radius:0 0 8px 8px; z-index:100; box-shadow:0 4px 12px rgba(0,0,0,.1); max-height:220px; overflow-y:auto;"></div>
        </div>

        <div id="selected-product" style="display:none; padding:.6rem .9rem; background:var(--c-bg); border:1px solid var(--c-primary); border-radius:6px; margin-bottom:14px; font-size:.85rem; color:var(--c-text);">
            <span id="selected-label"></span>
            <button type="button" onclick="clearProduct()" style="float:right; background:none; border:none; color:var(--c-muted); cursor:pointer; font-size:1rem;">✕</button>
        </div>

        <div class="form-group" style="margin-bottom:14px;">
            <label>Produktname <span style="color:#dc2626;">*</span></label>
            <input type="text" name="produktname" id="produktname"
                   value="{{ old('produktname') }}"
                   placeholder="z.B. Club-Mate 0,5 L Flasche"
                   required>
        </div>

        <div class="form-group" style="margin-bottom:16px;">
            <label>Anmerkung / Grund</label>
            <textarea name="info_text" rows="3"
                      placeholder="Warum fehlt das Produkt? Wie viele Einheiten werden benötigt?">{{ old('info_text') }}</textarea>
        </div>

        <button type="submit" class="btn btn-primary">Meldung senden</button>
    </form>
</div>

{{-- Meine bisherigen Meldungen --}}
@if($myReports->isNotEmpty())
<div class="mein-card" style="padding:0;">
    <div style="padding:.75rem 1rem; border-bottom:1px solid var(--c-border);">
        <strong style="font-size:.95rem;">Meine Meldungen</strong>
    </div>
    <table class="table" style="margin:0;">
        <thead>
            <tr>
                <th>Datum</th>
                <th>Produkt</th>
                <th>Anmerkung</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($myReports as $r)
            <tr>
                <td style="font-size:.85rem; color:var(--c-muted); white-space:nowrap;">
                    {{ $r->created_at->locale('de')->isoFormat('D. MMM, HH:mm') }}
                </td>
                <td>
                    <div style="font-weight:600; font-size:.9rem;">{{ $r->produktname }}</div>
                    @if($r->artikelnummer)
                        <div style="font-size:.78rem; color:var(--c-muted);">{{ $r->artikelnummer }}</div>
                    @endif
                </td>
                <td style="font-size:.85rem; color:var(--c-muted);">{{ Str::limit($r->info_text, 60) ?: '—' }}</td>
                <td>
                    <span style="font-size:.8rem; font-weight:600; color:{{ $r->statusColor() }};">
                        {{ $r->statusLabel() }}
                    </span>
                    @if($r->admin_note)
                        <div style="font-size:.78rem; color:var(--c-muted); margin-top:2px;">{{ $r->admin_note }}</div>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

<script>
const searchUrl = '{{ route('mein.produkt-suche') }}';
let searchTimer;

document.getElementById('produkt-search').addEventListener('input', function() {
    clearTimeout(searchTimer);
    const q = this.value.trim();
    if (q.length < 2) { hideList(); return; }
    searchTimer = setTimeout(() => fetchSuggestions(q), 200);
});

function fetchSuggestions(q) {
    fetch(searchUrl + '?q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(items => {
            const list = document.getElementById('autocomplete-list');
            if (!items.length) { hideList(); return; }
            list.innerHTML = items.map(p =>
                `<div onclick="selectProduct(${p.id}, '${escHtml(p.artikelnummer ?? '')}', '${escHtml(p.produktname)}')"
                      style="padding:.6rem 1rem; cursor:pointer; border-bottom:1px solid var(--c-border); font-size:.9rem;"
                      onmouseover="this.style.background='var(--c-bg)'"
                      onmouseout="this.style.background=''">
                    <span style="font-weight:600;">${escHtml(p.produktname)}</span>
                    ${p.artikelnummer ? `<span style="color:var(--c-muted); margin-left:.5rem; font-size:.8rem;">${escHtml(p.artikelnummer)}</span>` : ''}
                 </div>`
            ).join('');
            list.style.display = 'block';
        });
}

function selectProduct(id, artNr, name) {
    document.getElementById('product_id').value = id;
    document.getElementById('artikelnummer_hidden').value = artNr;
    document.getElementById('produktname').value = name;
    document.getElementById('produkt-search').value = name;
    document.getElementById('selected-label').textContent = name + (artNr ? ' (' + artNr + ')' : '');
    document.getElementById('selected-product').style.display = 'block';
    hideList();
}

function clearProduct() {
    document.getElementById('product_id').value = '';
    document.getElementById('artikelnummer_hidden').value = '';
    document.getElementById('selected-product').style.display = 'none';
    document.getElementById('produkt-search').value = '';
    document.getElementById('produktname').value = '';
}

function hideList() {
    document.getElementById('autocomplete-list').style.display = 'none';
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('#produkt-search') && !e.target.closest('#autocomplete-list')) hideList();
});
</script>

@endsection
