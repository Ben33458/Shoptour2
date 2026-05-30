@extends('admin.layout')

@section('title', 'Fehlende Produkte')

@section('content')
<div class="page-header">
    <h1>Fehlende Produkte</h1>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

{{-- Filter & Suche --}}
<div class="card" style="margin-bottom:1rem;">
    <div class="card-body" style="padding:.75rem 1rem;">
        <form method="GET" style="display:flex; gap:.75rem; flex-wrap:wrap; align-items:center;">
            <input type="text" name="q" value="{{ $search }}"
                   placeholder="Produkt suchen…"
                   style="padding:.35rem .7rem; border:1px solid var(--c-border); border-radius:6px; font-size:.85rem; width:200px;">

            <select name="status" style="padding:.35rem .7rem; border:1px solid var(--c-border); border-radius:6px; font-size:.85rem;">
                <option value="" {{ $status === '' ? 'selected' : '' }}>Alle Status</option>
                <option value="open"     {{ $status === 'open'     ? 'selected' : '' }}>Offen</option>
                <option value="ordered"  {{ $status === 'ordered'  ? 'selected' : '' }}>Bestellt</option>
                <option value="done"     {{ $status === 'done'     ? 'selected' : '' }}>Erledigt</option>
                <option value="rejected" {{ $status === 'rejected' ? 'selected' : '' }}>Abgelehnt</option>
            </select>

            <button type="submit" class="btn btn-secondary btn-sm">Filtern</button>
            @if($search || $status)
                <a href="{{ route('admin.missing-products.index') }}" class="btn btn-outline btn-sm">Zurücksetzen</a>
            @endif

            <span style="margin-left:auto; font-size:.85rem; color:var(--c-muted);">
                {{ $reports->total() }} Einträge
            </span>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding:0;">
        <table class="table">
            <thead>
                <tr>
                    <th>
                        <a href="?{{ http_build_query(array_merge(request()->query(), ['sort' => 'created_at', 'dir' => ($sort === 'created_at' && $dir === 'asc') ? 'desc' : 'asc'])) }}"
                           style="text-decoration:none; color:inherit;">
                            Datum {{ $sort === 'created_at' ? ($dir === 'asc' ? '↑' : '↓') : '' }}
                        </a>
                    </th>
                    <th>
                        <a href="?{{ http_build_query(array_merge(request()->query(), ['sort' => 'artikelnummer', 'dir' => ($sort === 'artikelnummer' && $dir === 'asc') ? 'desc' : 'asc'])) }}"
                           style="text-decoration:none; color:inherit;">
                            Art.-Nr. {{ $sort === 'artikelnummer' ? ($dir === 'asc' ? '↑' : '↓') : '' }}
                        </a>
                    </th>
                    <th>
                        <a href="?{{ http_build_query(array_merge(request()->query(), ['sort' => 'produktname', 'dir' => ($sort === 'produktname' && $dir === 'asc') ? 'desc' : 'asc'])) }}"
                           style="text-decoration:none; color:inherit;">
                            Produktname {{ $sort === 'produktname' ? ($dir === 'asc' ? '↑' : '↓') : '' }}
                        </a>
                    </th>
                    <th>Mitarbeiter</th>
                    <th>Anmerkung</th>
                    <th>Mögliche Lieferanten</th>
                    <th>
                        <a href="?{{ http_build_query(array_merge(request()->query(), ['sort' => 'status', 'dir' => ($sort === 'status' && $dir === 'asc') ? 'desc' : 'asc'])) }}"
                           style="text-decoration:none; color:inherit;">
                            Status {{ $sort === 'status' ? ($dir === 'asc' ? '↑' : '↓') : '' }}
                        </a>
                    </th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($reports as $report)
                @php
                    $statusColor = match($report->status) {
                        'open'     => '#d97706',
                        'ordered'  => '#2563eb',
                        'done'     => '#16a34a',
                        'rejected' => '#dc2626',
                        default    => '#64748b',
                    };
                    $statusLabel = match($report->status) {
                        'open'     => 'Offen',
                        'ordered'  => 'Bestellt',
                        'done'     => 'Erledigt',
                        'rejected' => 'Abgelehnt',
                        default    => $report->status,
                    };
                    // Gather suppliers from product relationship
                    $suppliers = [];
                    if ($report->product) {
                        foreach ($report->product->supplierProducts as $sp) {
                            if ($sp->supplier) $suppliers[] = $sp->supplier->name;
                        }
                    }
                @endphp
                <tr>
                    <td style="white-space:nowrap; font-size:.85rem; color:var(--c-muted);">
                        {{ $report->created_at->locale('de')->isoFormat('D. MMM YYYY, HH:mm') }}
                    </td>
                    <td style="font-size:.85rem; font-family:monospace;">
                        {{ $report->artikelnummer ?: ($report->product?->artikelnummer ?? '—') }}
                    </td>
                    <td>
                        <div style="font-weight:600; font-size:.9rem;">{{ $report->produktname }}</div>
                        @if($report->product)
                            <div style="font-size:.75rem; color:var(--c-muted);">verknüpft mit Artikel #{{ $report->product_id }}</div>
                        @endif
                    </td>
                    <td style="font-size:.85rem;">
                        {{ $report->employee?->first_name }} {{ $report->employee?->last_name }}
                    </td>
                    <td style="font-size:.85rem; color:var(--c-muted); max-width:180px;">
                        {{ $report->info_text ?: '—' }}
                    </td>
                    <td style="font-size:.85rem;">
                        @if(!empty($suppliers))
                            {{ implode(', ', $suppliers) }}
                        @else
                            <span style="color:var(--c-muted);">—</span>
                        @endif
                    </td>
                    <td>
                        <span style="font-size:.8rem; font-weight:600; color:{{ $statusColor }};">
                            {{ $statusLabel }}
                        </span>
                        @if($report->admin_note)
                            <div style="font-size:.75rem; color:var(--c-muted); margin-top:2px;">{{ $report->admin_note }}</div>
                        @endif
                    </td>
                    <td>
                        <button onclick="openEdit({{ $report->id }}, '{{ $report->status }}', '{{ addslashes($report->admin_note ?? '') }}')"
                                class="btn btn-outline btn-sm">Bearbeiten</button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="text-align:center; color:var(--c-muted); padding:2rem;">
                        Keine Meldungen vorhanden.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{ $reports->links() }}

{{-- Edit modal --}}
<div id="edit-overlay" style="display:none;position:fixed;inset:0;z-index:100;background:rgba(0,0,0,.5);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:10px;width:min(420px,95vw);box-shadow:0 8px 40px rgba(0,0,0,.2);">
        <div style="padding:16px 20px;border-bottom:1px solid var(--c-border);font-weight:700;">Status aktualisieren</div>
        <form id="edit-form" method="POST" style="padding:20px;">
            @csrf
            @method('PATCH')
            <div class="form-group" style="margin-bottom:14px;">
                <label>Status</label>
                <select name="status" id="edit-status">
                    <option value="open">Offen</option>
                    <option value="ordered">Bestellt</option>
                    <option value="done">Erledigt</option>
                    <option value="rejected">Abgelehnt</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:16px;">
                <label>Admin-Notiz</label>
                <textarea name="admin_note" id="edit-note" rows="3" placeholder="Interne Notiz (wird dem Mitarbeiter angezeigt)"></textarea>
            </div>
            <div style="display:flex;gap:.75rem;">
                <button type="submit" class="btn btn-primary">Speichern</button>
                <button type="button" onclick="closeEdit()" class="btn btn-outline">Abbrechen</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEdit(id, status, note) {
    document.getElementById('edit-form').action = '/admin/fehlende-produkte/' + id;
    document.getElementById('edit-status').value = status;
    document.getElementById('edit-note').value = note;
    document.getElementById('edit-overlay').style.display = 'flex';
}
function closeEdit() {
    document.getElementById('edit-overlay').style.display = 'none';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeEdit(); });
</script>

@endsection
