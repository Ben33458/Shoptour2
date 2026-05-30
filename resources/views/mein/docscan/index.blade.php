@extends('mein.layout')

@section('title', 'Dokument scannen')

@push('head')
<style>
.scan-step { display: none; }
.scan-step.active { display: block; }

.scan-dropzone {
    border: 2px dashed var(--c-border);
    border-radius: 12px;
    padding: 32px 20px;
    text-align: center;
    cursor: pointer;
    transition: border-color .2s, background .2s;
    margin-bottom: 12px;
}
.scan-dropzone:hover, .scan-dropzone.drag-over {
    border-color: var(--c-primary);
    background: var(--c-bg);
}
.scan-dropzone input[type=file] { display: none; }
.scan-dropzone-icon { font-size: 2rem; margin-bottom: 8px; }
.scan-dropzone-label { font-size: .9rem; color: var(--c-muted); }
.scan-dropzone-label strong { color: var(--c-text); }

.scan-btn {
    display: block; width: 100%; padding: 14px; border: none;
    border-radius: 10px; font-size: .95rem; font-weight: 600;
    cursor: pointer; transition: .15s; margin-bottom: 10px;
    text-align: center;
}
.scan-btn-primary   { background: var(--c-primary); color: #fff; }
.scan-btn-secondary { background: var(--c-bg); color: var(--c-text); border: 1px solid var(--c-border); }
.scan-btn-paperless { background: #059669; color: #fff; }
.scan-btn-lexoffice { background: #dc2626; color: #fff; }
.scan-btn-both      { background: #7c3aed; color: #fff; }
.scan-btn:disabled  { opacity: .5; cursor: not-allowed; }

.scan-preview-wrap {
    position: relative; user-select: none; touch-action: none;
    margin-bottom: 12px; border-radius: 8px; overflow: hidden;
}
#scanCanvas { display: block; width: 100%; border-radius: 8px; background: #000; }
.scan-handle {
    position: absolute; width: 28px; height: 28px;
    background: var(--c-primary); border-radius: 50%;
    border: 3px solid #fff; transform: translate(-50%,-50%);
    cursor: grab; z-index: 10;
    box-shadow: 0 2px 8px rgba(0,0,0,.4);
}
.scan-edge-handle {
    position: absolute; width: 20px; height: 20px;
    background: var(--c-primary); border-radius: 3px;
    border: 2px solid #fff; transform: translate(-50%,-50%);
    cursor: grab; z-index: 9;
    box-shadow: 0 2px 6px rgba(0,0,0,.3);
}

.scan-badge {
    display: inline-block; padding: 5px 12px; border-radius: 20px;
    font-size: .8rem; font-weight: 600; margin-bottom: 8px;
}
.scan-badge-rechnung { background: #fee2e2; color: #dc2626; }
.scan-badge-dokument { background: #d1fae5; color: #059669; }
.scan-badge-beides   { background: #ede9fe; color: #7c3aed; }
.scan-badge-unknown  { background: var(--c-bg); color: var(--c-muted); }

.scan-status-badge {
    display: inline-block; padding: 2px 8px; border-radius: 12px;
    font-size: .72rem; font-weight: 600;
}
.scan-status-pending { background: #fef3c7; color: #92400e; }
.scan-status-routed  { background: #d1fae5; color: #065f46; }
.scan-status-failed  { background: #fee2e2; color: #991b1b; }

.scan-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
.scan-table th { text-align: left; padding: 8px 10px; color: var(--c-muted);
                 font-size: .75rem; text-transform: uppercase; letter-spacing: .05em;
                 border-bottom: 1px solid var(--c-border); }
.scan-table td { padding: 10px 10px; border-bottom: 1px solid var(--c-border); vertical-align: middle; }
.scan-table tr:last-child td { border-bottom: none; }

.scan-assign-btns { display: flex; gap: 6px; flex-wrap: wrap; }
.scan-assign-btns button {
    padding: 4px 10px; border: none; border-radius: 6px;
    font-size: .78rem; font-weight: 600; cursor: pointer; transition: .15s;
}
.scan-assign-paperless { background: #059669; color: #fff; }
.scan-assign-lexoffice { background: #dc2626; color: #fff; }
.scan-assign-both      { background: #7c3aed; color: #fff; }
.scan-assign-btns button:disabled { opacity: .4; cursor: not-allowed; }

#scanErr { color: #dc2626; font-size: .85rem; margin: 8px 0; display: none; }
#scanInfo { color: var(--c-muted); font-size: .85rem; margin: 8px 0; display: none; }

.intern-badge { display:inline-block;padding:2px 8px;border-radius:12px;font-size:.72rem;font-weight:600;white-space:nowrap; }
.intern-badge-anlieferung         { background:#dbeafe;color:#1d4ed8; }
.intern-badge-lieferschein_kunden { background:#d1fae5;color:#065f46; }
.scan-assign-intern { background:#1d4ed8; color:#fff; }
</style>
@endpush

@section('content')

{{-- ── Upload Card ─────────────────────────────────────────────────────────── --}}
<div class="mein-card">
    <div class="mein-card-title">Dokument hochladen</div>

    {{-- Page queue indicator (shown across steps) --}}
    <div id="scanQueueBar" style="display:none;background:var(--c-bg);border:1px solid var(--c-primary);
         border-radius:8px;padding:8px 12px;margin-bottom:10px;display:none;align-items:center;gap:8px">
        <span style="color:var(--c-primary);font-size:.85rem;font-weight:600;flex:1">
            📄 <span id="scanQueueCount">0</span> Seite(n) im Stapel
        </span>
        <button onclick="clearPageQueue()"
                style="background:none;border:none;cursor:pointer;color:var(--c-muted);font-size:.78rem;padding:2px 6px">
            ✕ Verwerfen
        </button>
    </div>

    {{-- Step 1: File selection --}}
    <div id="scanStep1" class="scan-step active">
        <div class="scan-dropzone" id="scanDropzone" onclick="document.getElementById('scanFileInput').click()">
            <input type="file" id="scanFileInput" accept="image/*,application/pdf" multiple capture="environment">
            <div class="scan-dropzone-icon">📎</div>
            <div class="scan-dropzone-label">
                <strong>Datei(en) auswählen</strong><br>
                PDF oder Bilder (JPG, PNG) — mehrere Bilder werden zu einem PDF zusammengefügt
            </div>
        </div>
        <div id="scanSelectedFiles" style="font-size:.82rem;color:var(--c-muted);margin-bottom:8px;display:none"></div>
        <div id="scanErr"></div>
    </div>

    {{-- Step 2: Crop + Rotate (only for single images) --}}
    <div id="scanStep2" class="scan-step">
        <div style="display:flex;gap:8px;margin-bottom:10px;align-items:center;flex-wrap:wrap">
            <p style="font-size:.85rem;color:var(--c-muted);margin:0;flex:1">
                Ecken und Kanten ziehen zum Zuschneiden, Bild drehen falls nötig.
            </p>
            <button type="button" class="scan-btn scan-btn-secondary" id="scanResetBtn"
                    style="width:auto;padding:8px 14px;margin:0;white-space:nowrap">
                ⊞ Zurücksetzen
            </button>
            <button type="button" class="scan-btn scan-btn-secondary" id="scanRotateBtn"
                    style="width:auto;padding:8px 14px;margin:0;white-space:nowrap">
                ↻ Drehen
            </button>
        </div>
        <div class="scan-preview-wrap" id="scanPreviewWrap">
            <canvas id="scanCanvas"></canvas>
            <div class="scan-handle" id="sh0"></div>
            <div class="scan-handle" id="sh1"></div>
            <div class="scan-handle" id="sh2"></div>
            <div class="scan-handle" id="sh3"></div>
            <div class="scan-edge-handle" id="se0"></div>
            <div class="scan-edge-handle" id="se1"></div>
            <div class="scan-edge-handle" id="se2"></div>
            <div class="scan-edge-handle" id="se3"></div>
        </div>
        <button class="scan-btn scan-btn-primary" id="scanCropBtn">✂ Zuschneiden & Hochladen</button>
        <button class="scan-btn scan-btn-secondary" id="scanDownloadBtn">⬇ Herunterladen</button>
        <button class="scan-btn scan-btn-secondary" id="scanSkipCropBtn">Überspringen (ohne Zuschneiden)</button>
        <button class="scan-btn scan-btn-secondary" id="scanAddPageBtn"
                style="border-color:var(--c-primary);color:var(--c-primary)">
            📷 Als Seite hinzufügen &amp; weitere aufnehmen
        </button>
        {{-- Image editing controls --}}
        <div style="display:flex;flex-wrap:wrap;gap:10px;font-size:.82rem;color:var(--c-muted);margin-top:10px;align-items:center;padding:10px;background:var(--c-bg);border-radius:8px;border:1px solid var(--c-border)">
            <div style="display:flex;align-items:center;gap:6px">
                <label for="scanQualitySlider">Qualität</label>
                <input type="range" id="scanQualitySlider" min="30" max="100" value="92" step="1" style="width:90px">
                <span id="scanQualityValue" style="min-width:38px">92 %</span>
            </div>
            <div style="display:flex;align-items:center;gap:6px">
                <label>Helligkeit</label>
                <input type="range" id="scanBrightness" min="50" max="200" value="100" step="5" style="width:80px">
                <span id="scanBrightnessVal" style="min-width:38px">100 %</span>
            </div>
            <div style="display:flex;align-items:center;gap:6px">
                <label>Kontrast</label>
                <input type="range" id="scanContrast" min="50" max="200" value="100" step="5" style="width:80px">
                <span id="scanContrastVal" style="min-width:38px">100 %</span>
            </div>
            <label style="display:flex;gap:4px;align-items:center;cursor:pointer">
                <input type="checkbox" id="scanGrayscale"> Schwarzweiß
            </label>
        </div>
    </div>

    {{-- Step 3: Uploading --}}
    <div id="scanStep3" class="scan-step">
        <div style="text-align:center;padding:24px 0;color:var(--c-muted)">
            <div style="font-size:1.5rem;margin-bottom:8px">⏳</div>
            Wird hochgeladen…
        </div>
    </div>
</div>

{{-- ── Meine Uploads ───────────────────────────────────────────────────────── --}}
<div class="mein-card">
    <div class="mein-card-title">Meine letzten Uploads</div>
    <div style="overflow-x:auto">
    <table class="scan-table">
        <thead>
            <tr>
                <th>Datum</th>
                <th>Dokument</th>
                <th>Zugewiesen</th>
                <th>Ziel</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="myUploadsBody">
            @forelse($myUploads as $u)
            @if($u->trashed())
            <tr style="opacity:.45">
                <td style="white-space:nowrap">{{ $u->created_at->format('d.m.y H:i') }}</td>
                <td>
                    <div style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                         title="{{ $u->original_filename }}">
                        {{ $u->ai_metadata['title'] ?? $u->original_filename }}
                    </div>
                    @php $daysLeft = max(0, 7 - (int)$u->deleted_at->diffInDays(now())); @endphp
                    <div style="font-size:.72rem;color:#991b1b">Gelöscht{{ $daysLeft > 0 ? ' · '.$daysLeft.'T verbleibend' : '' }}</div>
                </td>
                <td>—</td>
                <td>—</td>
                <td></td>
            </tr>
            @else
            <tr id="upload-row-{{ $u->id }}">
                <td style="white-space:nowrap">{{ $u->created_at->format('d.m.y H:i') }}</td>
                <td>
                    <div style="font-weight:500;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                         title="{{ $u->original_filename }}">
                        {{ $u->ai_metadata['title'] ?? $u->original_filename }}
                    </div>
                    @if(!empty($u->ai_metadata['correspondent']))
                        <div style="font-size:.75rem;color:var(--c-muted)">{{ $u->ai_metadata['correspondent'] }}</div>
                    @endif
                    @if($u->status !== 'routed')
                        @php $isImgRow = !str_ends_with(strtolower($u->storage_path), '.pdf'); @endphp
                        <button onclick="openPreviewModal({{ $u->id }},'{{ route('mein.docscan.file', $u) }}',{{ $isImgRow ? 'true' : 'false' }},this.dataset.title,'{{ $u->status }}')"
                                data-title="{{ $u->ai_metadata['title'] ?? $u->original_filename }}"
                                style="background:none;border:none;color:var(--c-primary);cursor:pointer;font-size:.75rem;padding:0;text-decoration:underline">
                            Öffnen ↗
                        </button>
                    @endif
                </td>
                <td>
                    @if($u->status === 'routed' || $u->intern_typ)
                        <span class="scan-status-badge scan-status-routed">✓ Ja</span>
                    @else
                        <span style="color:var(--c-muted);font-size:.85rem">—</span>
                    @endif
                </td>
                <td id="upload-ziel-{{ $u->id }}">
                    @if($u->destination)
                        <div>{{ ['paperless'=>'📄 Paperless','lexoffice'=>'💶 Lexoffice','both'=>'📄💶 Beides'][$u->destination] ?? $u->destination }}</div>
                    @endif
                    @if($u->intern_typ)
                        <span class="intern-badge intern-badge-{{ $u->intern_typ }}" style="margin-top:2px">
                            {{ $u->intern_typ === 'anlieferung' ? '🏭 ' : '📦 ' }}{{ $u->intern_label ?? '#'.$u->intern_id }}
                        </span>
                    @endif
                    @if(!$u->destination && !$u->intern_typ)
                        <span style="color:var(--c-muted);font-size:.85rem">—</span>
                    @endif
                </td>
                <td>
                    @if($u->status !== 'routed')
                    <button onclick="openDeleteConfirm({{ $u->id }})" title="Löschen"
                            style="background:none;border:none;cursor:pointer;color:var(--c-muted);
                                   font-size:.9rem;padding:3px 6px;border-radius:6px;line-height:1;transition:.15s"
                            onmouseover="this.style.color='#dc2626';this.style.background='#fee2e2'"
                            onmouseout="this.style.color='var(--c-muted)';this.style.background='none'">🗑</button>
                    @endif
                </td>
            </tr>
            @endif
            @empty
            <tr id="myUploadsEmpty"><td colspan="5" style="color:var(--c-muted);text-align:center;padding:24px">Noch keine Uploads.</td></tr>
            @endforelse
        </tbody>
    </table>
    </div>
</div>

{{-- ── Admin: Alle ausstehenden Uploads ───────────────────────────────────── --}}
@if($pendingAll !== null)
<div class="mein-card">
    <div class="mein-card-title" id="pendingCardTitle">Ausstehende Zuweisung ({{ $pendingAll->count() }})</div>
    <div style="overflow-x:auto">
    <table class="scan-table">
        <thead>
            <tr>
                <th>Datum</th>
                <th>Mitarbeiter</th>
                <th>Dokument</th>
                <th>KI-Analyse</th>
                <th>Ziel</th>
            </tr>
        </thead>
        <tbody id="pendingAllBody">
            @forelse($pendingAll as $u)
            <tr id="pending-row-{{ $u->id }}">
                <td style="white-space:nowrap">{{ $u->created_at->format('d.m.y H:i') }}</td>
                <td style="white-space:nowrap">{{ $u->employee?->full_name ?? '—' }}</td>
                <td>
                    <div style="font-weight:500">
                        {{ $u->ai_metadata['title'] ?? $u->original_filename }}
                        @if($u->page_count > 1)
                            <span style="font-size:.72rem;color:var(--c-muted);margin-left:4px">{{ $u->page_count }}S.</span>
                        @endif
                    </div>
                    @if(!empty($u->ai_metadata['correspondent']))
                        <div style="font-size:.75rem;color:var(--c-muted)">{{ $u->ai_metadata['correspondent'] }}</div>
                    @endif
                    @if(!empty($u->ai_metadata['document_type']))
                        <div style="font-size:.75rem;color:var(--c-muted)">{{ $u->ai_metadata['document_type'] }}</div>
                    @endif
                    @php $isImg = !str_ends_with(strtolower($u->storage_path), '.pdf'); @endphp
                    <button onclick="openPreviewModal({{ $u->id }},'{{ route('mein.docscan.file', $u) }}',{{ $isImg ? 'true' : 'false' }},this.dataset.title,'pending')"
                            data-title="{{ $u->ai_metadata['title'] ?? $u->original_filename }}"
                            style="background:none;border:none;color:var(--c-primary);cursor:pointer;font-size:.75rem;padding:0;text-decoration:underline">
                        Dokument öffnen ↗
                    </button>
                </td>
                <td>
                    @if($u->ai_suggestion)
                        <span class="scan-badge scan-badge-{{ $u->ai_suggestion }}">
                            {{ ['rechnung'=>'💶 Rechnung','dokument'=>'📄 Dokument','beides'=>'📄💶 Beides','unknown'=>'?'][$u->ai_suggestion] ?? $u->ai_suggestion }}
                        </span>
                        @if(!empty($u->ai_metadata['amount']))
                            <div style="font-size:.75rem;color:var(--c-muted);margin-top:2px">{{ $u->ai_metadata['amount'] }}</div>
                        @endif
                        @if(!empty($u->ai_metadata['date']))
                            <div style="font-size:.75rem;color:var(--c-muted)">{{ $u->ai_metadata['date'] }}</div>
                        @endif
                        @if($u->ai_reason)
                            <div style="font-size:.72rem;color:var(--c-muted);font-style:italic;margin-top:2px">{{ $u->ai_reason }}</div>
                        @endif
                    @else —
                    @endif
                </td>
                <td id="pending-ziel-{{ $u->id }}">
                    @if($u->intern_typ)
                        <span class="intern-badge intern-badge-{{ $u->intern_typ }}">
                            {{ $u->intern_typ === 'anlieferung' ? '🏭 ' : '📦 ' }}{{ $u->intern_label ?? '#'.$u->intern_id }}
                        </span>
                    @else
                        <span style="color:var(--c-muted);font-size:.85rem">—</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="5" style="color:var(--c-muted);text-align:center;padding:24px">Keine ausstehenden Uploads.</td></tr>
            @endforelse
        </tbody>
    </table>
    </div>
</div>
@endif

{{-- ── Admin: Kürzlich gelöscht ─────────────────────────────────────────────── --}}
@if(!empty($deletedAll) && $deletedAll->count() > 0)
<div class="mein-card">
    <div class="mein-card-title">Kürzlich gelöscht ({{ $deletedAll->count() }})</div>
    <div style="overflow-x:auto">
    <table class="scan-table">
        <thead>
            <tr>
                <th>Gelöscht am</th>
                <th>Mitarbeiter</th>
                <th>Dokument</th>
                <th>KI-Vorschlag</th>
                <th>Gelöscht von</th>
                <th>Verbleibend</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($deletedAll as $u)
            <tr id="deleted-row-{{ $u->id }}" style="opacity:.6">
                <td style="white-space:nowrap">{{ $u->deleted_at->format('d.m.y H:i') }}</td>
                <td style="white-space:nowrap">{{ $u->employee?->full_name ?? '—' }}</td>
                <td>
                    <div style="font-weight:500;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                        {{ $u->ai_metadata['title'] ?? $u->original_filename }}
                    </div>
                    @if($u->page_count > 1)
                        <div style="font-size:.72rem;color:var(--c-muted)">{{ $u->page_count }} Seiten</div>
                    @endif
                </td>
                <td>
                    @if($u->ai_suggestion)
                        <span class="scan-badge scan-badge-{{ $u->ai_suggestion }}" style="font-size:.72rem">
                            {{ ['rechnung'=>'💶 Rechnung','dokument'=>'📄 Dokument','beides'=>'📄💶 Beides','unknown'=>'?'][$u->ai_suggestion] ?? '?' }}
                        </span>
                    @else —
                    @endif
                </td>
                <td>{{ $u->deletedByEmployee?->full_name ?? '—' }}</td>
                <td style="white-space:nowrap">
                    @php $daysLeft = max(0, 7 - (int)$u->deleted_at->diffInDays(now())); @endphp
                    <span style="font-size:.82rem;color:{{ $daysLeft <= 1 ? '#dc2626' : 'var(--c-muted)' }}">
                        {{ $daysLeft > 0 ? $daysLeft.'T' : 'heute' }}
                    </span>
                </td>
                <td>
                    <button onclick="restoreUpload({{ $u->id }})"
                            style="padding:5px 10px;border:1px solid var(--c-border);border-radius:7px;
                                   background:var(--c-bg);color:var(--c-text);cursor:pointer;font-size:.78rem;font-weight:600;white-space:nowrap">
                        ↺ Wiederherstellen
                    </button>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>
</div>
@endif

{{-- ── Delete Confirmation Dialog ──────────────────────────────────────────── --}}
<div id="deleteConfirmOverlay"
     style="position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:300;display:none;align-items:center;justify-content:center"
     onclick="if(event.target===this)closeDeleteConfirm()">
  <div style="background:var(--c-card);border-radius:16px;padding:28px 24px;max-width:340px;width:90%;
              box-shadow:0 8px 40px rgba(0,0,0,.35)">
    <div style="font-size:1.6rem;margin-bottom:10px;text-align:center">🗑</div>
    <div style="font-weight:700;font-size:1rem;margin-bottom:8px;text-align:center">Dokument löschen?</div>
    <p style="font-size:.84rem;color:var(--c-muted);text-align:center;margin:0 0 22px">
      Das Dokument wird als gelöscht markiert und kann innerhalb von 7&nbsp;Tagen
      durch einen Administrator wiederhergestellt werden.
    </p>
    <div style="display:flex;gap:10px">
      <button onclick="closeDeleteConfirm()"
              style="flex:1;padding:11px;border:1px solid var(--c-border);border-radius:9px;
                     background:var(--c-bg);color:var(--c-text);cursor:pointer;font-size:.9rem;font-weight:600">Abbrechen</button>
      <button onclick="confirmDelete()"
              style="flex:1;padding:11px;border:none;border-radius:9px;background:#dc2626;
                     color:#fff;cursor:pointer;font-size:.9rem;font-weight:600">Löschen</button>
    </div>
  </div>
</div>

{{-- ── Document Preview / Edit Modal ──────────────────────────────────────── --}}
<div id="docPreviewOverlay"
     style="position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:200;display:none;justify-content:flex-end"
     onclick="if(event.target===this)closePreviewModal()">
  <div style="width:min(720px,100vw);height:100%;background:var(--c-card);display:flex;flex-direction:column;
              box-shadow:-6px 0 32px rgba(0,0,0,.3);overflow:hidden">

    {{-- Header --}}
    <div style="padding:13px 16px;border-bottom:1px solid var(--c-border);display:flex;align-items:center;gap:8px;flex-shrink:0">
      <div style="flex:1;min-width:0;display:flex;align-items:center;gap:6px">
        <span id="previewDocTitle" style="font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.95rem;flex:1;min-width:0"></span>
        <button id="previewRenameBtn" onclick="startRename()" title="Titel bearbeiten"
                style="background:none;border:none;cursor:pointer;color:var(--c-muted);padding:3px 5px;flex-shrink:0;font-size:.85rem;line-height:1;border-radius:5px"
                onmouseover="this.style.background='var(--c-bg)'" onmouseout="this.style.background='none'">✏</button>
      </div>
      <button onclick="closePreviewModal()"
              style="background:none;border:none;cursor:pointer;font-size:1.4rem;color:var(--c-muted);padding:4px 6px;line-height:1;flex-shrink:0">✕</button>
    </div>

    {{-- Document viewer --}}
    <div id="previewDocBody"
         style="flex:1;min-height:0;background:#111;display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative;cursor:default">
      <iframe id="previewIframe" style="width:100%;height:100%;border:none;display:none" allowfullscreen></iframe>
      <img    id="previewImg"    style="max-width:100%;max-height:100%;object-fit:contain;display:none;transform-origin:center center;will-change:transform;user-select:none;-webkit-user-drag:none">
      {{-- Zoom controls --}}
      <div id="previewZoomControls"
           style="position:absolute;bottom:12px;right:12px;display:none;background:rgba(0,0,0,.6);
                  border-radius:10px;padding:5px 6px;gap:2px;align-items:center">
        <button onclick="zoomStep(-1)"
                style="background:none;border:none;color:#fff;cursor:pointer;font-size:1.15rem;padding:2px 8px;line-height:1;border-radius:6px">−</button>
        <span id="zoomLabel" style="color:#fff;font-size:.75rem;min-width:38px;text-align:center">100%</span>
        <button onclick="zoomStep(1)"
                style="background:none;border:none;color:#fff;cursor:pointer;font-size:1.15rem;padding:2px 8px;line-height:1;border-radius:6px">+</button>
        <button onclick="resetZoom()" title="Zoom zurücksetzen"
                style="background:none;border:none;color:#aaa;cursor:pointer;font-size:.9rem;padding:2px 8px;line-height:1;border-radius:6px">↺</button>
      </div>
    </div>

    {{-- Edit canvas (hidden until "Bearbeiten" clicked) --}}
    <div id="previewEditBody" style="flex:1;min-height:0;overflow:auto;padding:12px;display:none">
      <div style="display:flex;gap:8px;margin-bottom:10px;align-items:center">
        <p style="font-size:.85rem;color:var(--c-muted);margin:0;flex:1">Ecken ziehen zum Zuschneiden, Bild drehen falls nötig.</p>
        <button type="button" id="previewRotateBtn"
                style="padding:8px 14px;border:1px solid var(--c-border);border-radius:8px;background:var(--c-bg);
                       color:var(--c-text);cursor:pointer;font-size:.85rem;white-space:nowrap;flex-shrink:0">↻ Drehen</button>
      </div>
      <div style="position:relative;user-select:none;touch-action:none" id="previewCanvasWrap">
        <canvas id="previewCanvas" style="display:block;width:100%;border-radius:8px;background:#000"></canvas>
        <div class="scan-handle" id="ph0"></div>
        <div class="scan-handle" id="ph1"></div>
        <div class="scan-handle" id="ph2"></div>
        <div class="scan-handle" id="ph3"></div>
        <div class="scan-edge-handle" id="pe0"></div>
        <div class="scan-edge-handle" id="pe1"></div>
        <div class="scan-edge-handle" id="pe2"></div>
        <div class="scan-edge-handle" id="pe3"></div>
      </div>
    </div>

    {{-- Footer --}}
    <div style="padding:14px 16px;border-top:1px solid var(--c-border);flex-shrink:0;display:flex;flex-direction:column;gap:10px">

      {{-- Edit start button (image only) --}}
      <div id="previewEditControls" style="display:none">
        <button onclick="previewStartEdit()"
                style="width:100%;padding:10px;border:1px solid var(--c-border);border-radius:8px;
                       background:var(--c-bg);color:var(--c-text);cursor:pointer;font-size:.88rem;font-weight:600">
          ✂ Zuschneiden / Drehen
        </button>
      </div>

      {{-- Save / cancel (during edit) --}}
      <div id="previewSaveControls" style="display:none;gap:8px">
        <button onclick="previewSaveCrop()" id="previewSaveBtn"
                style="flex:1;padding:10px;border:none;border-radius:8px;background:var(--c-primary);
                       color:#fff;cursor:pointer;font-size:.88rem;font-weight:600">💾 Speichern</button>
        <button onclick="previewDownload()"
                style="padding:10px 14px;border:1px solid var(--c-border);border-radius:8px;
                       background:var(--c-bg);color:var(--c-text);cursor:pointer;font-size:.88rem"
                title="Herunterladen">⬇</button>
        <button onclick="previewCancelEdit()"
                style="flex:1;padding:10px;border:1px solid var(--c-border);border-radius:8px;
                       background:var(--c-bg);color:var(--c-text);cursor:pointer;font-size:.88rem">Abbrechen</button>
      </div>

      {{-- Delete button --}}
      <div id="previewDeleteArea">
        <button onclick="openDeleteConfirm(previewUploadId)"
                style="width:100%;padding:8px;border:1px solid #fca5a5;border-radius:8px;background:transparent;
                       color:#dc2626;cursor:pointer;font-size:.82rem;font-weight:500">
          🗑 Dokument löschen
        </button>
      </div>

      {{-- Assign buttons --}}
      <div id="previewAssignArea">
        <div class="scan-assign-btns" id="previewAssignBtns" style="gap:8px;flex-wrap:wrap">
          <button class="scan-assign-paperless"
                  style="flex:1;padding:11px 8px;font-size:.88rem"
                  onclick="assignFromPreview('paperless')">📄 Paperless</button>
          <button class="scan-assign-lexoffice"
                  style="flex:1;padding:11px 8px;font-size:.88rem"
                  onclick="assignFromPreview('lexoffice')">💶 Lexoffice</button>
          <button class="scan-assign-both"
                  style="flex:1;padding:11px 8px;font-size:.88rem"
                  onclick="assignFromPreview('both')">📄💶 Beides</button>
          <button class="scan-assign-intern"
                  style="flex:1;padding:11px 8px;font-size:.88rem"
                  onclick="openInternFromPreview()">📦 Lieferschein</button>
        </div>
        <div id="previewAssignStatus" style="display:none;font-size:.85rem;color:var(--c-muted);margin-top:8px;text-align:center"></div>
      </div>

    </div>
  </div>
</div>


{{-- ── Intern Zuordnen Modal ────────────────────────────────────────────────── --}}
<div id="internZuordnenOverlay"
     style="position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:400;display:none;align-items:center;justify-content:center"
     onclick="if(event.target===this)closeInternModal()">
  <div style="background:var(--c-card);border-radius:16px;padding:28px 24px;max-width:480px;width:95%;
              box-shadow:0 8px 40px rgba(0,0,0,.35);max-height:90vh;overflow-y:auto">
    <div style="font-weight:700;font-size:1.05rem;margin-bottom:16px">Intern zuordnen</div>

    {{-- Typ-Auswahl --}}
    <div style="margin-bottom:14px">
      <label style="font-size:.82rem;font-weight:600;color:var(--c-muted);display:block;margin-bottom:6px">Dokumenttyp</label>
      <div style="display:flex;gap:8px">
        <button id="internTypAnlieferung" onclick="setInternTyp('anlieferung')"
                style="flex:1;padding:9px;border-radius:9px;border:2px solid var(--c-primary);
                       background:var(--c-primary);color:#fff;cursor:pointer;font-size:.85rem;font-weight:600">
          🏭 Anlieferungsschein
        </button>
        <button id="internTypLieferschein" onclick="setInternTyp('lieferschein_kunden')"
                style="flex:1;padding:9px;border-radius:9px;border:2px solid var(--c-border);
                       background:var(--c-bg);color:var(--c-text);cursor:pointer;font-size:.85rem;font-weight:600">
          📦 Lieferschein (Kunde)
        </button>
      </div>
    </div>

    {{-- Suche --}}
    <div style="margin-bottom:10px">
      <label style="font-size:.82rem;font-weight:600;color:var(--c-muted);display:block;margin-bottom:6px" id="internSearchLabel">
        Lieferant / Datum / Lieferschein-Nr.
      </label>
      <input id="internSearchInput" type="text" placeholder="Suchen…"
             oninput="debounceInternSearch()"
             style="width:100%;padding:9px 12px;border:1px solid var(--c-border);border-radius:8px;
                    font-size:.88rem;background:var(--c-bg);color:var(--c-text);box-sizing:border-box">
    </div>

    {{-- Suchergebnisse --}}
    <div id="internSearchResults" style="max-height:200px;overflow-y:auto;border:1px solid var(--c-border);
         border-radius:8px;margin-bottom:12px;display:none"></div>

    {{-- Ausgewähltes Element --}}
    <div id="internSelectedWrap" style="display:none;margin-bottom:12px;padding:8px 12px;
         background:var(--c-bg);border-radius:8px;border:1px solid var(--c-primary)">
      <div style="font-size:.8rem;color:var(--c-muted);margin-bottom:2px">Ausgewählt:</div>
      <div id="internSelectedLabel" style="font-weight:600;font-size:.88rem"></div>
      <button onclick="clearInternSelection()" style="background:none;border:none;cursor:pointer;
              color:var(--c-muted);font-size:.75rem;padding:0;margin-top:2px;text-decoration:underline">Auswahl aufheben</button>
    </div>

    {{-- Quick-Create Anlieferung --}}
    <div id="internQuickCreate" style="display:none;border:1px solid var(--c-border);border-radius:10px;
         padding:14px;margin-bottom:12px">
      <div style="font-size:.82rem;font-weight:600;margin-bottom:10px;color:var(--c-muted)">+ Neue Anlieferung erfassen</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px">
        <div>
          <label style="font-size:.78rem;color:var(--c-muted);display:block;margin-bottom:3px">Lieferant *</label>
          <select id="qcSupplier" style="width:100%;padding:7px 8px;border:1px solid var(--c-border);border-radius:7px;
                  background:var(--c-bg);color:var(--c-text);font-size:.82rem">
            <option value="">Lieferant wählen…</option>
            @foreach(\App\Models\Supplier\Supplier::orderBy('name')->get() as $s)
            <option value="{{ $s->id }}">{{ $s->name }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label style="font-size:.78rem;color:var(--c-muted);display:block;margin-bottom:3px">Datum *</label>
          <input id="qcDate" type="date" value="{{ now()->format('Y-m-d') }}"
                 style="width:100%;padding:7px 8px;border:1px solid var(--c-border);border-radius:7px;
                        background:var(--c-bg);color:var(--c-text);font-size:.82rem">
        </div>
        <div>
          <label style="font-size:.78rem;color:var(--c-muted);display:block;margin-bottom:3px">Lieferschein-Nr.</label>
          <input id="qcLsNr" type="text" placeholder="Optional"
                 style="width:100%;padding:7px 8px;border:1px solid var(--c-border);border-radius:7px;
                        background:var(--c-bg);color:var(--c-text);font-size:.82rem">
        </div>
        <div>
          <label style="font-size:.78rem;color:var(--c-muted);display:block;margin-bottom:3px">Lager *</label>
          <select id="qcWarehouse" style="width:100%;padding:7px 8px;border:1px solid var(--c-border);border-radius:7px;
                  background:var(--c-bg);color:var(--c-text);font-size:.82rem">
            <option value="">Lager wählen…</option>
            @foreach(\App\Models\Inventory\Warehouse::orderBy('name')->get() as $w)
            <option value="{{ $w->id }}">{{ $w->name }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <button onclick="quickCreateAnlieferung()"
              style="width:100%;padding:9px;border:none;border-radius:8px;background:#059669;
                     color:#fff;cursor:pointer;font-size:.85rem;font-weight:600">
        Erstellen &amp; zuordnen
      </button>
      <div id="qcErr" style="color:#dc2626;font-size:.78rem;margin-top:6px;display:none"></div>
    </div>

    <div id="internModalErr" style="color:#dc2626;font-size:.82rem;margin-bottom:8px;display:none"></div>

    <div style="display:flex;gap:10px">
      <button onclick="closeInternModal()"
              style="flex:1;padding:11px;border:1px solid var(--c-border);border-radius:9px;
                     background:var(--c-bg);color:var(--c-text);cursor:pointer;font-size:.9rem;font-weight:600">Abbrechen</button>
      <button onclick="confirmInternZuordnen()" id="internConfirmBtn" disabled
              style="flex:1;padding:11px;border:none;border-radius:9px;background:#1d4ed8;
                     color:#fff;cursor:pointer;font-size:.9rem;font-weight:600;opacity:.5">Zuordnen</button>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
const CSRF         = document.querySelector('meta[name="csrf-token"]').content;
const storeUrl     = '{{ route("mein.docscan.store") }}';
const assignBaseUrl = '{{ url("mein/docscan") }}';
const isAdmin      = {{ in_array($employee->role, ['admin', 'manager']) ? 'true' : 'false' }};

function escapeHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── State ─────────────────────────────────────────────────────────────────────
let scanFiles    = [];
let pageQueue    = [];   // accumulated pages for multi-page document
let imgEl        = null;
let corners      = [];
let canvasW      = 0, canvasH = 0;
let activeHandle = null;
let rotationDeg  = 0;
const handles    = [0,1,2,3].map(i => document.getElementById('sh'+i));
const edgeHandles = [0,1,2,3].map(i => document.getElementById('se'+i));
const EDGE_PAIRS  = [[0,1],[1,2],[2,3],[3,0]];

function getQuality() { return parseInt(document.getElementById('scanQualitySlider').value) / 100; }
function getCanvasFilter() {
    const b = document.getElementById('scanBrightness').value / 100;
    const c = document.getElementById('scanContrast').value / 100;
    const g = document.getElementById('scanGrayscale').checked ? 'grayscale(1) ' : '';
    return `${g}brightness(${b}) contrast(${c})`;
}

// ── Step management ───────────────────────────────────────────────────────────
function scanShowStep(n) {
    [1,2,3].forEach(i => {
        document.getElementById('scanStep'+i).classList.toggle('active', i === n);
    });
}

function scanReset() {
    scanFiles   = [];
    pageQueue   = [];
    imgEl       = null;
    corners     = [];
    rotationDeg = 0;
    document.getElementById('scanFileInput').value = '';
    document.getElementById('scanSelectedFiles').style.display = 'none';
    document.getElementById('scanSelectedFiles').textContent   = '';
    updateQueueInfo();
    scanShowStep(1);
}

function updateQueueInfo() {
    const bar = document.getElementById('scanQueueBar');
    if (pageQueue.length > 0) {
        document.getElementById('scanQueueCount').textContent = pageQueue.length;
        bar.style.display = 'flex';
    } else {
        bar.style.display = 'none';
    }
    const n = pageQueue.length + 1;
    document.getElementById('scanCropBtn').textContent  = pageQueue.length > 0
        ? `✂ Zuschneiden & hochladen (${n} Seiten)` : '✂ Zuschneiden & Hochladen';
    document.getElementById('scanSkipCropBtn').textContent = pageQueue.length > 0
        ? `Überspringen & hochladen (${n} Seiten)` : 'Überspringen (ohne Zuschneiden)';
}

function clearPageQueue() {
    pageQueue = [];
    updateQueueInfo();
}

// ── File selection ────────────────────────────────────────────────────────────
document.getElementById('scanFileInput').addEventListener('change', e => {
    scanFiles = Array.from(e.target.files);
    if (!scanFiles.length) return;

    const info = document.getElementById('scanSelectedFiles');
    info.textContent = scanFiles.length === 1
        ? scanFiles[0].name
        : `${scanFiles.length} Dateien ausgewählt`;
    info.style.display = 'block';

    if (scanFiles.length === 1 && scanFiles[0].type.startsWith('image/')) {
        // Single image → show crop step (allows adding more pages after)
        showCropStep(scanFiles[0]);
    } else if (pageQueue.length > 0) {
        // Multiple new files + existing queue → combine all and upload
        const allFiles = [...pageQueue, ...scanFiles];
        pageQueue = [];
        doUpload(allFiles);
    } else {
        doUpload(scanFiles);
    }
});

// Drag & drop
const dz = document.getElementById('scanDropzone');
dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('drag-over'); });
dz.addEventListener('dragleave', () => dz.classList.remove('drag-over'));
dz.addEventListener('drop', e => {
    e.preventDefault();
    dz.classList.remove('drag-over');
    const dt = e.dataTransfer;
    if (dt.files.length) {
        document.getElementById('scanFileInput').files = dt.files;
        document.getElementById('scanFileInput').dispatchEvent(new Event('change'));
    }
});

// ── Crop step ─────────────────────────────────────────────────────────────────
function showCropStep(file) {
    rotationDeg = 0;
    scanShowStep(2);
    const reader = new FileReader();
    reader.onload = ev => {
        imgEl = new Image();
        imgEl.onload = setupCanvas;
        imgEl.src = ev.target.result;
    };
    reader.readAsDataURL(file);
}

function getRotatedDimensions() {
    const rot = rotationDeg % 180;
    return rot === 0
        ? {w: imgEl.naturalWidth,  h: imgEl.naturalHeight}
        : {w: imgEl.naturalHeight, h: imgEl.naturalWidth};
}

function setupCanvas() {
    const canvas = document.getElementById('scanCanvas');
    const wrap = document.getElementById('scanPreviewWrap');
    const maxW = wrap.clientWidth;
    const {w: srcW, h: srcH} = getRotatedDimensions();
    const scale = Math.min(maxW / srcW, 520 / srcH);
    canvasW = Math.round(srcW * scale);
    canvasH = Math.round(srcH * scale);
    canvas.width = canvasW;
    canvas.height = canvasH;

    const m = Math.round(Math.min(canvasW, canvasH) * 0.05);
    corners = [{x:m,y:m},{x:canvasW-m,y:m},{x:canvasW-m,y:canvasH-m},{x:m,y:canvasH-m}];
    drawOverlay();
    positionHandles();
    bindHandles();
}

function drawRotatedImage(ctx) {
    ctx.save();
    ctx.translate(canvasW / 2, canvasH / 2);
    ctx.rotate(rotationDeg * Math.PI / 180);
    ctx.filter = getCanvasFilter();
    const {w: srcW, h: srcH} = getRotatedDimensions();
    const scale = Math.min(canvasW / srcW, canvasH / srcH);
    const dw = imgEl.naturalWidth  * scale;
    const dh = imgEl.naturalHeight * scale;
    ctx.drawImage(imgEl, -dw / 2, -dh / 2, dw, dh);
    ctx.restore();
}

function drawOverlay() {
    const canvas = document.getElementById('scanCanvas');
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvasW, canvasH);
    drawRotatedImage(ctx);
    ctx.fillStyle = 'rgba(0,0,0,0.45)';
    ctx.fillRect(0, 0, canvasW, canvasH);
    ctx.save();
    ctx.beginPath();
    corners.forEach((c,i) => i === 0 ? ctx.moveTo(c.x,c.y) : ctx.lineTo(c.x,c.y));
    ctx.closePath();
    ctx.clip();
    drawRotatedImage(ctx);
    ctx.restore();
    ctx.strokeStyle = '#2563eb';
    ctx.lineWidth = 2;
    ctx.beginPath();
    corners.forEach((c,i) => i === 0 ? ctx.moveTo(c.x,c.y) : ctx.lineTo(c.x,c.y));
    ctx.closePath();
    ctx.stroke();
}

document.getElementById('scanRotateBtn').addEventListener('click', () => {
    rotationDeg = (rotationDeg + 90) % 360;
    setupCanvas();
});

function positionHandles() {
    const canvas = document.getElementById('scanCanvas');
    const sx = canvas.offsetWidth / canvasW;
    const sy = canvas.offsetHeight / canvasH;
    handles.forEach((h,i) => {
        h.style.left = (corners[i].x * sx) + 'px';
        h.style.top  = (corners[i].y * sy) + 'px';
    });
    EDGE_PAIRS.forEach(([a,b], i) => {
        const mx = (corners[a].x + corners[b].x) / 2;
        const my = (corners[a].y + corners[b].y) / 2;
        edgeHandles[i].style.left = (mx * sx) + 'px';
        edgeHandles[i].style.top  = (my * sy) + 'px';
    });
}

function bindHandles() {
    handles.forEach((h,i) => {
        h.onpointerdown = e => { activeHandle = i; h.setPointerCapture(e.pointerId); e.preventDefault(); };
        h.onpointermove = e => {
            if (activeHandle !== i) return;
            const r = document.getElementById('scanCanvas').getBoundingClientRect();
            const sx = canvasW / document.getElementById('scanCanvas').offsetWidth;
            const sy = canvasH / document.getElementById('scanCanvas').offsetHeight;
            corners[i].x = Math.max(0, Math.min(canvasW, (e.clientX - r.left) * sx));
            corners[i].y = Math.max(0, Math.min(canvasH, (e.clientY - r.top) * sy));
            drawOverlay();
            positionHandles();
            e.preventDefault();
        };
        h.onpointerup = () => { activeHandle = null; };
    });
    edgeHandles.forEach((h, i) => {
        const [ia, ib] = EDGE_PAIRS[i];
        let lastX, lastY;
        h.onpointerdown = e => { h.setPointerCapture(e.pointerId); lastX = e.clientX; lastY = e.clientY; e.preventDefault(); };
        h.onpointermove = e => {
            if (!h.hasPointerCapture(e.pointerId)) return;
            const canvas = document.getElementById('scanCanvas');
            const sx = canvasW / canvas.offsetWidth;
            const sy = canvasH / canvas.offsetHeight;
            const dx = (e.clientX - lastX) * sx;
            const dy = (e.clientY - lastY) * sy;
            lastX = e.clientX; lastY = e.clientY;
            for (const idx of [ia, ib]) {
                corners[idx].x = Math.max(0, Math.min(canvasW, corners[idx].x + dx));
                corners[idx].y = Math.max(0, Math.min(canvasH, corners[idx].y + dy));
            }
            drawOverlay(); positionHandles(); e.preventDefault();
        };
        h.onpointerup = h.onpointercancel = () => {};
    });
}

document.getElementById('scanResetBtn').addEventListener('click', () => {
    const m = Math.round(Math.min(canvasW, canvasH) * 0.05);
    corners = [{x:m,y:m},{x:canvasW-m,y:m},{x:canvasW-m,y:canvasH-m},{x:m,y:canvasH-m}];
    drawOverlay(); positionHandles();
});

document.getElementById('scanDownloadBtn').addEventListener('click', () => {
    perspectiveCrop().toBlob(blob => {
        const url = URL.createObjectURL(blob);
        Object.assign(document.createElement('a'), {href: url, download: 'scan.jpg'}).click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    }, 'image/jpeg', getQuality());
});

document.getElementById('scanQualitySlider').addEventListener('input', e =>
    document.getElementById('scanQualityValue').textContent = e.target.value + ' %');
document.getElementById('scanBrightness').addEventListener('input', e => {
    document.getElementById('scanBrightnessVal').textContent = e.target.value + ' %';
    if (imgEl) drawOverlay();
});
document.getElementById('scanContrast').addEventListener('input', e => {
    document.getElementById('scanContrastVal').textContent = e.target.value + ' %';
    if (imgEl) drawOverlay();
});
document.getElementById('scanGrayscale').addEventListener('change', () => { if (imgEl) drawOverlay(); });

document.getElementById('scanCropBtn').addEventListener('click', async () => {
    const blob = await new Promise(res => perspectiveCrop().toBlob(res, 'image/jpeg', getQuality()));
    const allFiles = [...pageQueue, new File([blob], `seite_${pageQueue.length+1}.jpg`, {type:'image/jpeg'})];
    pageQueue = [];
    await doUpload(allFiles);
});

document.getElementById('scanSkipCropBtn').addEventListener('click', async () => {
    let file;
    if (rotationDeg === 0) {
        file = scanFiles[0];
    } else {
        const {w: srcW, h: srcH} = getRotatedDimensions();
        const off = document.createElement('canvas');
        off.width = srcW; off.height = srcH;
        const offCtx = off.getContext('2d');
        offCtx.translate(srcW / 2, srcH / 2);
        offCtx.rotate(rotationDeg * Math.PI / 180);
        offCtx.filter = getCanvasFilter();
        offCtx.drawImage(imgEl, -imgEl.naturalWidth / 2, -imgEl.naturalHeight / 2);
        const blob = await new Promise(res => off.toBlob(res, 'image/jpeg', getQuality()));
        file = new File([blob], `seite_${pageQueue.length+1}.jpg`, {type:'image/jpeg'});
    }
    const allFiles = [...pageQueue, file];
    pageQueue = [];
    doUpload(allFiles);
});

document.getElementById('scanAddPageBtn').addEventListener('click', async () => {
    // Crop/rotate current image and add to queue, then open picker for next
    const blob = await new Promise(res => perspectiveCrop().toBlob(res, 'image/jpeg', getQuality()));
    pageQueue.push(new File([blob], `seite_${pageQueue.length+1}.jpg`, {type:'image/jpeg'}));
    updateQueueInfo();
    // Reset file picker, go back to step 1, and immediately open picker
    scanFiles = []; imgEl = null; rotationDeg = 0;
    document.getElementById('scanSelectedFiles').style.display = 'none';
    document.getElementById('scanFileInput').value = '';
    scanShowStep(1);
    setTimeout(() => document.getElementById('scanFileInput').click(), 80);
});

function perspectiveCrop() {
    const {w: srcW, h: srcH} = getRotatedDimensions();

    // Full-res offscreen canvas — rotation applied, drawImage stays on GPU
    const off = document.createElement('canvas');
    off.width = srcW; off.height = srcH;
    const offCtx = off.getContext('2d');
    offCtx.translate(srcW / 2, srcH / 2);
    offCtx.rotate(rotationDeg * Math.PI / 180);
    offCtx.filter = getCanvasFilter();
    offCtx.drawImage(imgEl, -imgEl.naturalWidth / 2, -imgEl.naturalHeight / 2);

    // Scale display-space corners to full-res coordinates
    const sx = srcW / canvasW;
    const sy = srcH / canvasH;
    const sc = corners.map(c => ({x: c.x * sx, y: c.y * sy}));

    // Tight bounding box of the selection in full-res space
    const x0 = Math.max(0, Math.round(Math.min(sc[0].x, sc[1].x, sc[2].x, sc[3].x)));
    const y0 = Math.max(0, Math.round(Math.min(sc[0].y, sc[1].y, sc[2].y, sc[3].y)));
    const x1 = Math.min(srcW, Math.round(Math.max(sc[0].x, sc[1].x, sc[2].x, sc[3].x)));
    const y1 = Math.min(srcH, Math.round(Math.max(sc[0].y, sc[1].y, sc[2].y, sc[3].y)));
    const dstW = x1 - x0;
    const dstH = y1 - y0;

    // GPU-accelerated drawImage crop — no pixel loop, no quality loss
    const dst = document.createElement('canvas');
    dst.width = dstW; dst.height = dstH;
    dst.getContext('2d').drawImage(off, x0, y0, dstW, dstH, 0, 0, dstW, dstH);
    return dst;
}

// ── Upload (phase 1: store; phase 2: analyze async) ───────────────────────────
async function doUpload(files) {
    scanShowStep(3);

    const fd = new FormData();
    fd.append('_token', CSRF);
    files.forEach(f => fd.append('files[]', f));

    let storeData;
    try {
        const resp = await fetch(storeUrl, {method:'POST', body:fd});
        storeData = await resp.json();
        if (!resp.ok) throw new Error(storeData.error || 'Fehler beim Hochladen');
    } catch(e) {
        scanShowStep(1);
        const err = document.getElementById('scanErr');
        err.textContent = e.message;
        err.style.display = 'block';
        setTimeout(() => err.style.display = 'none', 5000);
        return;
    }

    // File is stored — add row immediately and reset form
    addPendingRow(storeData);
    scanReset();

    // Trigger AI analysis in background (non-blocking)
    triggerAnalyze(storeData.id);
}

function addPendingRow(data) {
    const tbody = document.getElementById('myUploadsBody');
    if (!tbody) return;
    document.getElementById('myUploadsEmpty')?.remove();

    const title   = escapeHtml(data.filename);
    const fileUrl = escapeHtml(data.file_url);
    const tr = document.createElement('tr');
    tr.id = 'upload-row-' + data.id;
    tr.innerHTML = `
        <td style="white-space:nowrap">${data.created_at}</td>
        <td>
            <div style="font-weight:500;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                 title="${title}">${title}</div>
            <button onclick="openPreviewModal(${data.id},'${fileUrl}',${data.is_image},this.dataset.title,'pending')"
                    data-title="${title}"
                    style="background:none;border:none;color:var(--c-primary);cursor:pointer;font-size:.75rem;padding:0;text-decoration:underline">
                Öffnen ↗
            </button>
        </td>
        <td><span class="scan-status-badge scan-status-pending">Ausstehend</span></td>
        <td id="upload-ai-${data.id}"><span style="color:var(--c-muted);font-size:.8rem">⏳ …</span></td>
        <td><button onclick="openDeleteConfirm(${data.id})" title="Löschen"
                style="background:none;border:none;cursor:pointer;color:var(--c-muted);font-size:.9rem;
                       padding:3px 6px;border-radius:6px;line-height:1;transition:.15s"
                onmouseover="this.style.color='#dc2626';this.style.background='#fee2e2'"
                onmouseout="this.style.color='var(--c-muted)';this.style.background='none'">🗑</button></td>`;
    tbody.insertBefore(tr, tbody.firstChild);
}

async function triggerAnalyze(id) {
    const fd = new FormData();
    fd.append('_token', CSRF);
    try {
        const resp = await fetch(`${assignBaseUrl}/${id}/analyze`, {method:'POST', body:fd});
        const data = await resp.json();
        if (resp.ok) updateRowAnalysis(id, data);
        else         updateRowAnalysisFailed(id);
    } catch(e) {
        updateRowAnalysisFailed(id);
    }
}

function updateRowAnalysis(id, data) {
    const aiCell = document.getElementById('upload-ai-' + id);
    if (!aiCell) return;

    const m          = data.metadata || {};
    const badgeClass = {rechnung:'scan-badge-rechnung',dokument:'scan-badge-dokument',beides:'scan-badge-beides',unknown:'scan-badge-unknown'};
    const badgeLbl   = {rechnung:'💶 Rechnung',dokument:'📄 Dokument',beides:'📄💶 Beides',unknown:'?'};
    const sug        = data.suggestion || 'unknown';
    const amt        = m.amount ? `<div style="font-size:.75rem;color:var(--c-muted);margin-top:2px">${escapeHtml(m.amount)}</div>` : '';
    aiCell.innerHTML = `<span class="scan-badge ${badgeClass[sug]??'scan-badge-unknown'}" title="${escapeHtml(data.reason||'')}">${badgeLbl[sug]??'?'}</span>${amt}`;

    const tr = document.getElementById('upload-row-' + id);
    if (!tr) return;

    if (data.page_count) {
        const pc = document.getElementById('upload-pc-' + id);
        if (pc) pc.textContent = data.page_count;
    }
    if (m.title) {
        const titleDiv = tr.querySelectorAll('td')[1]?.querySelector('div');
        if (titleDiv) { titleDiv.textContent = m.title; titleDiv.title = m.title; }
        const openBtn = tr.querySelector('button[data-title]');
        if (openBtn) openBtn.dataset.title = m.title;
        if (m.correspondent) {
            const td = tr.querySelectorAll('td')[1];
            if (td && !td.querySelector('.corr-div')) {
                const d = document.createElement('div');
                d.className = 'corr-div';
                d.style.cssText = 'font-size:.75rem;color:var(--c-muted)';
                d.textContent = m.correspondent;
                titleDiv?.after(d);
            }
        }
    }
}

function updateRowAnalysisFailed(id) {
    const c = document.getElementById('upload-ai-' + id);
    if (c) c.innerHTML = '<span style="color:var(--c-muted);font-size:.8rem" title="KI-Analyse fehlgeschlagen">⚠</span>';
}

// ── Preview Modal ─────────────────────────────────────────────────────────────
let previewUploadId  = null;
let previewIsImage   = false;
let previewImgEl     = null;
let previewCorners   = [];
let previewCanvasW   = 0, previewCanvasH = 0;
let previewRotDeg    = 0;
let previewActHandle = null;
const previewHandles     = [0,1,2,3].map(i => document.getElementById('ph'+i));
const previewEdgeHandles = [0,1,2,3].map(i => document.getElementById('pe'+i));
const PREVIEW_EDGE_PAIRS = [[0,1],[1,2],[2,3],[3,0]];
const replaceBaseUrl = '{{ url("mein/docscan") }}';

function openPreviewModal(id, fileUrl, isImage, title, status) {
    previewUploadId = id;
    previewIsImage  = isImage;

    document.getElementById('previewDocTitle').textContent = title || '';
    document.getElementById('docPreviewOverlay').style.display = 'flex';
    document.body.style.overflow = 'hidden';

    const canAssign = isAdmin && status === 'pending';

    // Reset to viewer mode
    document.getElementById('previewDocBody').style.display    = 'flex';
    document.getElementById('previewEditBody').style.display   = 'none';
    document.getElementById('previewEditControls').style.display = (isImage && isAdmin) ? 'block' : 'none';
    document.getElementById('previewSaveControls').style.display = 'none';
    document.getElementById('previewDeleteArea').style.display  = 'block';
    document.getElementById('previewAssignArea').style.display  = canAssign ? 'block' : 'none';
    document.getElementById('previewAssignBtns').querySelectorAll('button').forEach(b => b.disabled = false);
    document.getElementById('previewAssignStatus').style.display = 'none';
    document.getElementById('previewZoomControls').style.display = isImage ? 'flex' : 'none';

    resetZoom();

    if (isImage) {
        const img = document.getElementById('previewImg');
        img.src = fileUrl;
        img.style.display = 'block';
        document.getElementById('previewIframe').style.display = 'none';
    } else {
        document.getElementById('previewIframe').src = fileUrl;
        document.getElementById('previewIframe').style.display = 'block';
        document.getElementById('previewImg').style.display    = 'none';
    }
}

function closePreviewModal() {
    document.getElementById('docPreviewOverlay').style.display = 'none';
    document.body.style.overflow = '';
    document.getElementById('previewIframe').src = '';
    document.getElementById('previewImg').src    = '';
    previewUploadId = null; previewImgEl = null; previewRotDeg = 0;
    resetZoom();
}

function previewStartEdit() {
    previewRotDeg = 0;
    previewImgEl  = new Image();
    previewImgEl.onload = () => {
        document.getElementById('previewDocBody').style.display    = 'none';
        document.getElementById('previewEditBody').style.display   = 'block';
        document.getElementById('previewEditControls').style.display = 'none';
        document.getElementById('previewSaveControls').style.display = 'flex';
        document.getElementById('previewAssignArea').style.display  = 'none';
        document.getElementById('previewDeleteArea').style.display  = 'none';
        requestAnimationFrame(previewSetupCanvas);
    };
    previewImgEl.src = document.getElementById('previewImg').src;
}

function previewCancelEdit() {
    document.getElementById('previewDocBody').style.display    = 'flex';
    document.getElementById('previewEditBody').style.display   = 'none';
    document.getElementById('previewEditControls').style.display = 'block';
    document.getElementById('previewSaveControls').style.display = 'none';
    document.getElementById('previewAssignArea').style.display  = 'block';
    document.getElementById('previewDeleteArea').style.display  = 'block';
}

document.getElementById('previewRotateBtn').addEventListener('click', () => {
    previewRotDeg = (previewRotDeg + 90) % 360;
    previewSetupCanvas();
});

function previewGetRotDims() {
    const rot = previewRotDeg % 180;
    return rot === 0
        ? {w: previewImgEl.naturalWidth,  h: previewImgEl.naturalHeight}
        : {w: previewImgEl.naturalHeight, h: previewImgEl.naturalWidth};
}

function previewSetupCanvas() {
    const canvas = document.getElementById('previewCanvas');
    const wrap   = document.getElementById('previewCanvasWrap');
    const maxW   = wrap.clientWidth || 400;
    const {w: srcW, h: srcH} = previewGetRotDims();
    const scale  = Math.min(maxW / srcW, 600 / srcH);
    previewCanvasW = Math.round(srcW * scale);
    previewCanvasH = Math.round(srcH * scale);
    canvas.width   = previewCanvasW;
    canvas.height  = previewCanvasH;
    const m = Math.round(Math.min(previewCanvasW, previewCanvasH) * 0.05);
    previewCorners = [{x:m,y:m},{x:previewCanvasW-m,y:m},{x:previewCanvasW-m,y:previewCanvasH-m},{x:m,y:previewCanvasH-m}];
    previewDrawOverlay();
    previewPositionHandles();
    previewBindHandles();
}

function previewDrawRotImg(ctx) {
    ctx.save();
    ctx.translate(previewCanvasW / 2, previewCanvasH / 2);
    ctx.rotate(previewRotDeg * Math.PI / 180);
    ctx.filter = getCanvasFilter();
    const {w: srcW, h: srcH} = previewGetRotDims();
    const scale = Math.min(previewCanvasW / srcW, previewCanvasH / srcH);
    ctx.drawImage(previewImgEl,
        -previewImgEl.naturalWidth  * scale / 2,
        -previewImgEl.naturalHeight * scale / 2,
         previewImgEl.naturalWidth  * scale,
         previewImgEl.naturalHeight * scale);
    ctx.restore();
}

function previewDrawOverlay() {
    const canvas = document.getElementById('previewCanvas');
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, previewCanvasW, previewCanvasH);
    previewDrawRotImg(ctx);
    ctx.fillStyle = 'rgba(0,0,0,0.45)';
    ctx.fillRect(0, 0, previewCanvasW, previewCanvasH);
    ctx.save();
    ctx.beginPath();
    previewCorners.forEach((c,i) => i===0 ? ctx.moveTo(c.x,c.y) : ctx.lineTo(c.x,c.y));
    ctx.closePath(); ctx.clip();
    previewDrawRotImg(ctx);
    ctx.restore();
    ctx.strokeStyle = '#2563eb'; ctx.lineWidth = 2;
    ctx.beginPath();
    previewCorners.forEach((c,i) => i===0 ? ctx.moveTo(c.x,c.y) : ctx.lineTo(c.x,c.y));
    ctx.closePath(); ctx.stroke();
}

function previewPositionHandles() {
    const canvas = document.getElementById('previewCanvas');
    const sx = canvas.offsetWidth  / previewCanvasW;
    const sy = canvas.offsetHeight / previewCanvasH;
    previewHandles.forEach((h,i) => {
        h.style.left = (previewCorners[i].x * sx) + 'px';
        h.style.top  = (previewCorners[i].y * sy) + 'px';
    });
    PREVIEW_EDGE_PAIRS.forEach(([a,b], i) => {
        const mx = (previewCorners[a].x + previewCorners[b].x) / 2;
        const my = (previewCorners[a].y + previewCorners[b].y) / 2;
        previewEdgeHandles[i].style.left = (mx * sx) + 'px';
        previewEdgeHandles[i].style.top  = (my * sy) + 'px';
    });
}

function previewBindHandles() {
    previewHandles.forEach((h,i) => {
        h.onpointerdown = e => { previewActHandle = i; h.setPointerCapture(e.pointerId); e.preventDefault(); };
        h.onpointermove = e => {
            if (previewActHandle !== i) return;
            const r  = document.getElementById('previewCanvas').getBoundingClientRect();
            const sx = previewCanvasW / document.getElementById('previewCanvas').offsetWidth;
            const sy = previewCanvasH / document.getElementById('previewCanvas').offsetHeight;
            previewCorners[i].x = Math.max(0, Math.min(previewCanvasW, (e.clientX - r.left) * sx));
            previewCorners[i].y = Math.max(0, Math.min(previewCanvasH, (e.clientY - r.top)  * sy));
            previewDrawOverlay(); previewPositionHandles(); e.preventDefault();
        };
        h.onpointerup = () => { previewActHandle = null; };
    });
    previewEdgeHandles.forEach((h, i) => {
        const [ia, ib] = PREVIEW_EDGE_PAIRS[i];
        let lastX, lastY;
        h.onpointerdown = e => { h.setPointerCapture(e.pointerId); lastX = e.clientX; lastY = e.clientY; e.preventDefault(); };
        h.onpointermove = e => {
            if (!h.hasPointerCapture(e.pointerId)) return;
            const canvas = document.getElementById('previewCanvas');
            const sx = previewCanvasW / canvas.offsetWidth;
            const sy = previewCanvasH / canvas.offsetHeight;
            const dx = (e.clientX - lastX) * sx;
            const dy = (e.clientY - lastY) * sy;
            lastX = e.clientX; lastY = e.clientY;
            for (const idx of [ia, ib]) {
                previewCorners[idx].x = Math.max(0, Math.min(previewCanvasW, previewCorners[idx].x + dx));
                previewCorners[idx].y = Math.max(0, Math.min(previewCanvasH, previewCorners[idx].y + dy));
            }
            previewDrawOverlay(); previewPositionHandles(); e.preventDefault();
        };
        h.onpointerup = h.onpointercancel = () => {};
    });
}

function previewPerspectiveCrop() {
    const {w: srcW, h: srcH} = previewGetRotDims();

    const off = document.createElement('canvas');
    off.width = srcW; off.height = srcH;
    const offCtx = off.getContext('2d');
    offCtx.translate(srcW / 2, srcH / 2);
    offCtx.rotate(previewRotDeg * Math.PI / 180);
    offCtx.filter = getCanvasFilter();
    offCtx.drawImage(previewImgEl, -previewImgEl.naturalWidth / 2, -previewImgEl.naturalHeight / 2);

    const sx = srcW / previewCanvasW;
    const sy = srcH / previewCanvasH;
    const sc = previewCorners.map(c => ({x: c.x * sx, y: c.y * sy}));

    const x0 = Math.max(0, Math.round(Math.min(sc[0].x, sc[1].x, sc[2].x, sc[3].x)));
    const y0 = Math.max(0, Math.round(Math.min(sc[0].y, sc[1].y, sc[2].y, sc[3].y)));
    const x1 = Math.min(srcW, Math.round(Math.max(sc[0].x, sc[1].x, sc[2].x, sc[3].x)));
    const y1 = Math.min(srcH, Math.round(Math.max(sc[0].y, sc[1].y, sc[2].y, sc[3].y)));

    const dst = document.createElement('canvas');
    dst.width = x1 - x0; dst.height = y1 - y0;
    dst.getContext('2d').drawImage(off, x0, y0, dst.width, dst.height, 0, 0, dst.width, dst.height);
    return dst;
}

async function previewSaveCrop() {
    const btn = document.getElementById('previewSaveBtn');
    btn.disabled = true; btn.textContent = '…';

    let canvas;
    // If corners haven't moved from default (full image), just rotate without crop
    const {w: srcW, h: srcH} = previewGetRotDims();
    const m = Math.round(Math.min(previewCanvasW, previewCanvasH) * 0.05);
    const isDefault = previewCorners.every((c, i) => {
        const def = [{x:m,y:m},{x:previewCanvasW-m,y:m},{x:previewCanvasW-m,y:previewCanvasH-m},{x:m,y:previewCanvasH-m}][i];
        return Math.abs(c.x - def.x) < 3 && Math.abs(c.y - def.y) < 3;
    });

    if (isDefault && previewRotDeg !== 0) {
        // Rotation only — no crop
        const off = document.createElement('canvas');
        off.width = srcW; off.height = srcH;
        const offCtx = off.getContext('2d');
        offCtx.translate(srcW / 2, srcH / 2);
        offCtx.rotate(previewRotDeg * Math.PI / 180);
        offCtx.filter = getCanvasFilter();
        offCtx.drawImage(previewImgEl, -previewImgEl.naturalWidth / 2, -previewImgEl.naturalHeight / 2);
        canvas = off;
    } else {
        canvas = previewPerspectiveCrop();
    }

    const blob = await new Promise(res => canvas.toBlob(res, 'image/jpeg', getQuality()));
    const fd = new FormData();
    fd.append('_token', CSRF);
    fd.append('file', new File([blob], 'edited.jpg', {type:'image/jpeg'}));

    try {
        const resp = await fetch(`${replaceBaseUrl}/${previewUploadId}/replace-file`, {method:'POST', body:fd});
        if (!resp.ok) throw new Error((await resp.json()).error || 'Fehler');
        // Update the preview image with the saved blob
        const newUrl = URL.createObjectURL(blob);
        document.getElementById('previewImg').src = newUrl;
        previewCancelEdit();
    } catch(e) {
        alert('Fehler beim Speichern: ' + e.message);
    } finally {
        btn.disabled = false; btn.textContent = '💾 Speichern';
    }
}

function previewDownload() {
    previewPerspectiveCrop().toBlob(blob => {
        const url = URL.createObjectURL(blob);
        Object.assign(document.createElement('a'), {href: url, download: 'scan.jpg'}).click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    }, 'image/jpeg', getQuality());
}

async function assignFromPreview(destination) {
    const btns      = document.getElementById('previewAssignBtns');
    const statusDiv = document.getElementById('previewAssignStatus');
    btns.querySelectorAll('button').forEach(b => b.disabled = true);
    statusDiv.textContent = ''; statusDiv.style.display = 'none';

    const fd = new FormData();
    fd.append('_token', CSRF);
    fd.append('destination', destination);

    try {
        const resp = await fetch(`${assignBaseUrl}/${previewUploadId}/assign`, {method:'POST', body:fd});
        const data = await resp.json();
        if (!resp.ok) throw new Error(data.error || 'Fehler');

        const row = document.getElementById('pending-row-' + previewUploadId);
        if (row) row.remove();

        const myRow = document.getElementById('upload-row-' + previewUploadId);
        if (myRow) {
            const destLabels = {paperless:'📄 Paperless', lexoffice:'💶 Lexoffice', both:'📄💶 Beides'};
            const cells = myRow.querySelectorAll('td');
            if (cells[2]) cells[2].innerHTML = '<span class="scan-status-badge scan-status-routed">✓ Ja</span>';
            if (cells[3]) cells[3].textContent = destLabels[destination] ?? destination;
            // File is deleted after routing — hide Öffnen and delete buttons
            if (cells[1]) { const ob = cells[1].querySelector('button[data-title]'); if (ob) ob.remove(); }
            if (cells[4]) cells[4].innerHTML = '';
        }

        statusDiv.textContent = '✓ Erfolgreich zugewiesen';
        statusDiv.style.display = 'block';
        setTimeout(closePreviewModal, 1200);
    } catch(e) {
        btns.querySelectorAll('button').forEach(b => b.disabled = false);
        statusDiv.textContent = '⚠ ' + e.message;
        statusDiv.style.display = 'block';
    }
}

// ── Image Zoom ────────────────────────────────────────────────────────────────
let zoomScale = 1, zoomX = 0, zoomY = 0;
let isPanning = false, panStartX = 0, panStartY = 0, panStartTX = 0, panStartTY = 0;
let lastPinchDist = 0;

function applyZoom() {
    const img = document.getElementById('previewImg');
    img.style.transform = `translate(${zoomX}px, ${zoomY}px) scale(${zoomScale})`;
    const lbl = document.getElementById('zoomLabel');
    if (lbl) lbl.textContent = Math.round(zoomScale * 100) + '%';
    const body = document.getElementById('previewDocBody');
    body.style.cursor = zoomScale > 1 ? (isPanning ? 'grabbing' : 'grab') : 'default';
}

function resetZoom() {
    zoomScale = 1; zoomX = 0; zoomY = 0; isPanning = false;
    applyZoom();
}

function zoomStep(dir) {
    const factor = dir > 0 ? 1.3 : 1/1.3;
    zoomScale = Math.max(1, Math.min(8, zoomScale * factor));
    if (zoomScale === 1) { zoomX = 0; zoomY = 0; }
    applyZoom();
}

(function setupZoom() {
    const body = document.getElementById('previewDocBody');

    // Mouse wheel zoom
    body.addEventListener('wheel', e => {
        if (!previewIsImage) return;
        e.preventDefault();
        const factor = e.deltaY < 0 ? 1.2 : 1/1.2;
        const r = body.getBoundingClientRect();
        const cx = e.clientX - r.left - r.width / 2;
        const cy = e.clientY - r.top  - r.height / 2;
        const newScale = Math.max(1, Math.min(8, zoomScale * factor));
        const f = newScale / zoomScale;
        zoomX = cx - (cx - zoomX) * f;
        zoomY = cy - (cy - zoomY) * f;
        zoomScale = newScale;
        if (zoomScale === 1) { zoomX = 0; zoomY = 0; }
        applyZoom();
    }, {passive: false});

    // Mouse drag pan
    body.addEventListener('mousedown', e => {
        if (!previewIsImage || zoomScale <= 1) return;
        isPanning = true; e.preventDefault();
        panStartX = e.clientX; panStartY = e.clientY;
        panStartTX = zoomX;    panStartTY = zoomY;
        body.style.cursor = 'grabbing';
    });
    document.addEventListener('mousemove', e => {
        if (!isPanning) return;
        zoomX = panStartTX + (e.clientX - panStartX);
        zoomY = panStartTY + (e.clientY - panStartY);
        applyZoom();
    });
    document.addEventListener('mouseup', () => {
        if (isPanning) { isPanning = false; applyZoom(); }
    });

    // Touch: pinch zoom + pan
    body.addEventListener('touchstart', e => {
        if (!previewIsImage) return;
        if (e.touches.length === 2) {
            lastPinchDist = Math.hypot(
                e.touches[1].clientX - e.touches[0].clientX,
                e.touches[1].clientY - e.touches[0].clientY);
            e.preventDefault();
        } else if (e.touches.length === 1 && zoomScale > 1) {
            isPanning = true;
            panStartX = e.touches[0].clientX; panStartY = e.touches[0].clientY;
            panStartTX = zoomX; panStartTY = zoomY;
        }
    }, {passive: false});

    body.addEventListener('touchmove', e => {
        if (!previewIsImage) return;
        if (e.touches.length === 2) {
            const dist = Math.hypot(
                e.touches[1].clientX - e.touches[0].clientX,
                e.touches[1].clientY - e.touches[0].clientY);
            const f = dist / lastPinchDist;
            const r = body.getBoundingClientRect();
            const midX = (e.touches[0].clientX + e.touches[1].clientX) / 2 - r.left - r.width / 2;
            const midY = (e.touches[0].clientY + e.touches[1].clientY) / 2 - r.top  - r.height / 2;
            zoomX = midX - (midX - zoomX) * f;
            zoomY = midY - (midY - zoomY) * f;
            zoomScale = Math.max(1, Math.min(8, zoomScale * f));
            if (zoomScale === 1) { zoomX = 0; zoomY = 0; }
            lastPinchDist = dist;
            applyZoom(); e.preventDefault();
        } else if (e.touches.length === 1 && isPanning) {
            zoomX = panStartTX + (e.touches[0].clientX - panStartX);
            zoomY = panStartTY + (e.touches[0].clientY - panStartY);
            applyZoom(); e.preventDefault();
        }
    }, {passive: false});

    body.addEventListener('touchend', () => { isPanning = false; });

    // Double-click/tap to toggle zoom
    let lastTap = 0;
    body.addEventListener('dblclick', e => {
        if (!previewIsImage) return;
        if (zoomScale > 1) { resetZoom(); return; }
        const r = body.getBoundingClientRect();
        const cx = e.clientX - r.left - r.width / 2;
        const cy = e.clientY - r.top  - r.height / 2;
        zoomScale = 2.5;
        zoomX = -cx * 1.5; zoomY = -cy * 1.5;
        applyZoom();
    });
})();

// ── Delete Confirm ────────────────────────────────────────────────────────────
let deleteTargetId = null;
const deleteBaseUrl = '{{ url("mein/docscan") }}';

function openDeleteConfirm(id) {
    deleteTargetId = id;
    document.getElementById('deleteConfirmOverlay').style.display = 'flex';
}

function closeDeleteConfirm() {
    document.getElementById('deleteConfirmOverlay').style.display = 'none';
    deleteTargetId = null;
}

async function confirmDelete() {
    if (!deleteTargetId) return;
    const id = deleteTargetId;
    closeDeleteConfirm();

    const fd = new FormData();
    fd.append('_token', CSRF);
    fd.append('_method', 'DELETE');

    try {
        const resp = await fetch(`${deleteBaseUrl}/${id}`, {method:'POST', body:fd});
        if (!resp.ok) throw new Error((await resp.json()).error || 'Fehler');

        // Gray out the row in myUploads table
        const row = document.getElementById('upload-row-' + id);
        if (row) {
            row.style.opacity = '.45';
            row.removeAttribute('id');
            const cells = row.querySelectorAll('td');
            if (cells[4]) cells[4].innerHTML = '<span class="scan-status-badge" style="background:#fee2e2;color:#991b1b">Gelöscht</span><div style="font-size:.7rem;color:var(--c-muted)">7T verbleibend</div>';
            if (cells[5]) cells[5].textContent = '—';
            if (cells[6]) cells[6].innerHTML = '';
        }

        // Remove from admin pending table
        const pendingRow = document.getElementById('pending-row-' + id);
        if (pendingRow) pendingRow.remove();

        // Close modal if open for this upload
        if (previewUploadId === id) closePreviewModal();

    } catch(e) {
        alert('Fehler beim Löschen: ' + e.message);
    }
}

async function restoreUpload(id) {
    const btn = document.querySelector(`#deleted-row-${id} button`);
    if (btn) { btn.disabled = true; btn.textContent = '…'; }

    const fd = new FormData();
    fd.append('_token', CSRF);

    try {
        const resp = await fetch(`${deleteBaseUrl}/${id}/restore`, {method:'POST', body:fd});
        if (!resp.ok) throw new Error((await resp.json()).error || 'Fehler');
        window.location.reload();
    } catch(e) {
        if (btn) { btn.disabled = false; btn.textContent = '↺ Wiederherstellen'; }
        alert('Fehler: ' + e.message);
    }
}

// ── Rename ────────────────────────────────────────────────────────────────────
function startRename() {
    const titleSpan = document.getElementById('previewDocTitle');
    if (!titleSpan) return;
    const oldTitle = titleSpan.textContent;
    const input = document.createElement('input');
    input.type = 'text';
    input.value = oldTitle;
    input.style.cssText = 'flex:1;min-width:0;padding:3px 8px;border:1px solid var(--c-primary);border-radius:6px;' +
                          'font-size:.9rem;font-weight:600;background:var(--c-bg);color:var(--c-text);outline:none';
    titleSpan.replaceWith(input);
    const btn = document.getElementById('previewRenameBtn');
    if (btn) { btn.textContent = '✓'; btn.onclick = () => saveRename(input.value, oldTitle); }
    input.onkeydown = e => {
        if (e.key === 'Enter') saveRename(input.value, oldTitle);
        if (e.key === 'Escape') cancelRename(oldTitle);
    };
    input.focus(); input.select();
}

async function saveRename(newTitle, oldTitle) {
    newTitle = newTitle.trim();
    if (!newTitle) { cancelRename(oldTitle); return; }

    const fd = new FormData();
    fd.append('_token', CSRF);
    fd.append('title', newTitle);

    try {
        const resp = await fetch(`${assignBaseUrl}/${previewUploadId}/rename`, {method:'POST', body:fd});
        if (!resp.ok) throw new Error();
    } catch(e) {
        alert('Fehler beim Speichern');
        cancelRename(oldTitle);
        return;
    }

    // Restore span with new title
    _applyRenameTitle(newTitle);

    // Update table row
    const tr = document.getElementById('upload-row-' + previewUploadId);
    if (tr) {
        const titleDiv = tr.querySelectorAll('td')[1]?.querySelector('div');
        if (titleDiv) { titleDiv.textContent = newTitle; titleDiv.title = newTitle; }
        const openBtn = tr.querySelector('button[data-title]');
        if (openBtn) openBtn.dataset.title = newTitle;
    }
    const pendingRow = document.getElementById('pending-row-' + previewUploadId);
    if (pendingRow) {
        const td = pendingRow.querySelectorAll('td')[2];
        const d = td?.querySelector('[style*="font-weight"]');
        if (d) d.textContent = newTitle;
        const ob = td?.querySelector('button[data-title]');
        if (ob) ob.dataset.title = newTitle;
    }
}

function cancelRename(oldTitle) { _applyRenameTitle(oldTitle); }

function _applyRenameTitle(title) {
    const input = document.querySelector('#docPreviewOverlay div input[type=text]');
    if (input) {
        const span = document.createElement('span');
        span.id = 'previewDocTitle';
        span.style.cssText = 'font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.95rem;flex:1;min-width:0';
        span.textContent = title;
        input.replaceWith(span);
    }
    const btn = document.getElementById('previewRenameBtn');
    if (btn) { btn.textContent = '✏'; btn.onclick = startRename; }
}

// ── Admin: Assign ─────────────────────────────────────────────────────────────
async function assignUpload(id, destination) {
    const btns = document.getElementById('assign-btns-' + id);
    const statusDiv = document.getElementById('assign-status-' + id);
    btns.querySelectorAll('button').forEach(b => b.disabled = true);

    const fd = new FormData();
    fd.append('_token', CSRF);
    fd.append('destination', destination);

    try {
        const resp = await fetch(`${assignBaseUrl}/${id}/assign`, {method:'POST', body:fd});
        const data = await resp.json();
        if (!resp.ok) throw new Error(data.error || 'Fehler');

        // Remove row from pending table
        const row = document.getElementById('pending-row-' + id);
        if (row) row.remove();

        // Update status in my-uploads table if row exists
        const myRow = document.getElementById('upload-row-' + id);
        if (myRow) {
            const destLabels = {paperless:'📄 Paperless', lexoffice:'💶 Lexoffice', both:'📄💶 Beides'};
            const cells = myRow.querySelectorAll('td');
            if (cells[2]) cells[2].innerHTML = '<span class="scan-status-badge scan-status-routed">✓ Ja</span>';
            if (cells[3]) cells[3].textContent = destLabels[destination] ?? destination;
            if (cells[1]) { const ob = cells[1].querySelector('button[data-title]'); if (ob) ob.remove(); }
            if (cells[4]) cells[4].innerHTML = '';
        }
    } catch (e) {
        btns.querySelectorAll('button').forEach(b => b.disabled = false);
        statusDiv.textContent = '⚠ ' + e.message;
        statusDiv.style.display = 'block';
    }
}

// ── Intern Zuordnen ───────────────────────────────────────────────────────────
let internUploadId = null;
let internTyp      = 'anlieferung';
let internSelectedId   = null;
let internSelectedLabel = null;
let internSearchTimer  = null;

function openInternFromPreview() {
    const id = previewUploadId;
    if (!id) return;
    // Keep preview open underneath (intern modal z-index is higher)
    openInternModal(id);
    // Default to Lieferschein Kunden when coming from preview
    setInternTyp('lieferschein_kunden');
}

function openInternModal(uploadId) {
    internUploadId = uploadId;
    internSelectedId = null;
    internSelectedLabel = null;
    document.getElementById('internSearchInput').value = '';
    document.getElementById('internSearchResults').style.display = 'none';
    document.getElementById('internSearchResults').innerHTML = '';
    document.getElementById('internSelectedWrap').style.display = 'none';
    document.getElementById('internModalErr').style.display = 'none';
    document.getElementById('internConfirmBtn').disabled = true;
    document.getElementById('internConfirmBtn').style.opacity = '.5';
    document.getElementById('internQuickCreate').style.display = 'none';
    setInternTyp('anlieferung');
    document.getElementById('internZuordnenOverlay').style.display = 'flex';
}

function closeInternModal() {
    document.getElementById('internZuordnenOverlay').style.display = 'none';
    internUploadId = null;
}

function setInternTyp(typ) {
    internTyp = typ;
    internSelectedId = null;
    internSelectedLabel = null;
    document.getElementById('internSelectedWrap').style.display = 'none';
    document.getElementById('internSearchResults').style.display = 'none';
    document.getElementById('internSearchResults').innerHTML = '';
    document.getElementById('internSearchInput').value = '';
    document.getElementById('internConfirmBtn').disabled = true;
    document.getElementById('internConfirmBtn').style.opacity = '.5';

    const btnA = document.getElementById('internTypAnlieferung');
    const btnL = document.getElementById('internTypLieferschein');
    const qc   = document.getElementById('internQuickCreate');

    if (typ === 'anlieferung') {
        btnA.style.background = 'var(--c-primary)';
        btnA.style.color = '#fff';
        btnA.style.borderColor = 'var(--c-primary)';
        btnL.style.background = 'var(--c-bg)';
        btnL.style.color = 'var(--c-text)';
        btnL.style.borderColor = 'var(--c-border)';
        document.getElementById('internSearchLabel').textContent = 'Lieferant / Datum / Lieferschein-Nr.';
        qc.style.display = 'block';
    } else {
        btnL.style.background = 'var(--c-primary)';
        btnL.style.color = '#fff';
        btnL.style.borderColor = 'var(--c-primary)';
        btnA.style.background = 'var(--c-bg)';
        btnA.style.color = 'var(--c-text)';
        btnA.style.borderColor = 'var(--c-border)';
        document.getElementById('internSearchLabel').textContent = 'Bestellnummer / Kundenname';
        qc.style.display = 'none';
    }
}

function debounceInternSearch() {
    clearTimeout(internSearchTimer);
    internSearchTimer = setTimeout(runInternSearch, 300);
}

async function runInternSearch() {
    const q = document.getElementById('internSearchInput').value.trim();
    const url = internTyp === 'anlieferung'
        ? '{{ route("mein.docscan.search.anlieferungen") }}?q=' + encodeURIComponent(q)
        : '{{ route("mein.docscan.search.bestellungen") }}?q=' + encodeURIComponent(q);

    try {
        const resp = await fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
        const items = await resp.json();
        const box = document.getElementById('internSearchResults');
        box.innerHTML = '';

        if (items.length === 0) {
            box.innerHTML = '<div style="padding:10px 12px;font-size:.82rem;color:var(--c-muted)">Keine Treffer.</div>';
        } else {
            items.forEach(item => {
                const row = document.createElement('div');
                row.style.cssText = 'padding:9px 12px;cursor:pointer;font-size:.82rem;border-bottom:1px solid var(--c-border)';
                row.textContent = item.label;
                if (item.status) {
                    const badge = document.createElement('span');
                    badge.style.cssText = 'margin-left:6px;font-size:.7rem;padding:1px 6px;border-radius:10px;background:#f1f5f9;color:#475569';
                    badge.textContent = item.status;
                    row.appendChild(badge);
                }
                row.onclick = () => selectInternItem(item.id, item.label);
                row.onmouseover = () => { row.style.background = 'var(--c-bg)'; };
                row.onmouseout  = () => { row.style.background = ''; };
                box.appendChild(row);
            });
        }
        box.style.display = 'block';
    } catch(e) {
        console.error('Intern search error', e);
    }
}

function selectInternItem(id, label) {
    internSelectedId = id;
    internSelectedLabel = label;
    document.getElementById('internSelectedLabel').textContent = label;
    document.getElementById('internSelectedWrap').style.display = 'block';
    document.getElementById('internSearchResults').style.display = 'none';
    document.getElementById('internConfirmBtn').disabled = false;
    document.getElementById('internConfirmBtn').style.opacity = '1';
}

function clearInternSelection() {
    internSelectedId = null;
    internSelectedLabel = null;
    document.getElementById('internSelectedWrap').style.display = 'none';
    document.getElementById('internConfirmBtn').disabled = true;
    document.getElementById('internConfirmBtn').style.opacity = '.5';
}

async function quickCreateAnlieferung() {
    const supplier   = document.getElementById('qcSupplier').value;
    const warehouse  = document.getElementById('qcWarehouse').value;
    const date       = document.getElementById('qcDate').value;
    const lsNr       = document.getElementById('qcLsNr').value;
    const errDiv     = document.getElementById('qcErr');
    errDiv.style.display = 'none';

    if (!supplier || !warehouse || !date) {
        errDiv.textContent = 'Bitte Lieferant, Datum und Lager ausfüllen.';
        errDiv.style.display = 'block';
        return;
    }

    try {
        const resp = await fetch('{{ route("mein.docscan.anlieferung.quick-create") }}', {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content},
            body: JSON.stringify({supplier_id: supplier, warehouse_id: warehouse, arrived_at: date, lieferschein_nr: lsNr || null})
        });
        const data = await resp.json();
        if (!resp.ok) throw new Error(data.message || 'Fehler beim Erstellen');
        selectInternItem(data.id, data.label);
    } catch(e) {
        errDiv.textContent = '⚠ ' + e.message;
        errDiv.style.display = 'block';
    }
}

async function confirmInternZuordnen() {
    if (!internSelectedId || !internUploadId) return;
    const errDiv = document.getElementById('internModalErr');
    errDiv.style.display = 'none';

    try {
        const resp = await fetch('/mein/docscan/' + internUploadId + '/intern-zuordnen', {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content},
            body: JSON.stringify({intern_typ: internTyp, intern_id: internSelectedId})
        });
        const data = await resp.json();
        if (!resp.ok) throw new Error(data.error || 'Fehler');

        // Update Ziel cell in both tables
        const icon = internTyp === 'anlieferung' ? '🏭' : '📦';
        const cssClass = internTyp === 'anlieferung' ? 'intern-badge-anlieferung' : 'intern-badge-lieferschein_kunden';
        const badgeHtml = `<span class="intern-badge ${cssClass}">${icon} ${internSelectedLabel || '#'+internSelectedId}</span>`;

        const uploadZiel = document.getElementById('upload-ziel-' + internUploadId);
        if (uploadZiel) uploadZiel.innerHTML = badgeHtml;

        const pendingZiel = document.getElementById('pending-ziel-' + internUploadId);
        if (pendingZiel) pendingZiel.innerHTML = badgeHtml;

        // Mark as zugewiesen in my-uploads table
        const uploadRow = document.getElementById('upload-row-' + internUploadId);
        if (uploadRow) {
            const cells = uploadRow.querySelectorAll('td');
            if (cells[2]) cells[2].innerHTML = '<span class="scan-status-badge scan-status-routed">✓ Ja</span>';
        }

        closeInternModal();
        closePreviewModal();
    } catch(e) {
        errDiv.textContent = '⚠ ' + e.message;
        errDiv.style.display = 'block';
    }
}

</script>
@endpush
