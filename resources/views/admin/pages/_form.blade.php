{{-- Gemeinsames Formular-Partial für create + edit --}}
<div class="card">
    <div style="padding:20px;display:flex;flex-direction:column;gap:16px">

        {{-- Titel --}}
        <div class="form-group">
            <label>Titel *</label>
            <input type="text" name="title" id="page-title"
                   value="{{ old('title', $page?->title) }}"
                   class="form-control" required maxlength="255"
                   placeholder="z.B. Heimdienst">
        </div>

        {{-- Slug --}}
        <div class="form-group">
            <label>
                URL-Pfad (Slug)
                @if($page)
                    <span style="color:var(--c-muted);font-size:.8em;margin-left:4px">— kann bei bestehenden Seiten nicht geändert werden</span>
                @else
                    <span style="color:var(--c-muted);font-size:.8em;margin-left:4px">— wird automatisch aus dem Titel generiert, kann überschrieben werden</span>
                @endif
            </label>
            <div style="display:flex;align-items:center;gap:8px">
                <span style="color:var(--c-muted);white-space:nowrap">/seite/</span>
                <input type="text" name="slug" id="page-slug"
                       value="{{ old('slug', $page?->slug) }}"
                       class="form-control"
                       placeholder="wird aus Titel erzeugt"
                       pattern="[a-z0-9\-]+"
                       title="Nur Kleinbuchstaben, Zahlen und Bindestriche"
                       {{ $page ? 'readonly style=background:#f5f5f5;color:#888' : '' }}>
            </div>
            @error('slug')
                <div class="hint" style="color:var(--c-danger)">{{ $message }}</div>
            @enderror
        </div>

        {{-- Menü + Reihenfolge + Status (Zeile) --}}
        <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start">
            <div class="form-group" style="flex:2;min-width:220px">
                <label>Navigation / Menü *</label>
                <select name="menu" required>
                    @foreach(\App\Models\Page::MENUS as $value => $label)
                        <option value="{{ $value }}"
                            @selected(old('menu', $page?->menu ?? 'none') === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="flex:0 0 120px">
                <label>Reihenfolge</label>
                <input type="number" name="sort_order" min="0" max="9999" step="10"
                       value="{{ old('sort_order', $page?->sort_order ?? 0) }}"
                       class="form-control">
                <div class="hint">Kleinere Zahl = weiter oben</div>
            </div>
            <div class="form-group" style="flex:0 0 auto;padding-top:28px">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" value="1"
                           {{ old('active', $page?->active ?? true) ? 'checked' : '' }}>
                    Seite aktiv (öffentlich sichtbar)
                </label>
            </div>
        </div>

        {{-- Inhalt / Editor --}}
        <div class="form-group">
            <label>Inhalt</label>
            <div id="editor-wrapper">
                <div id="quill-editor"></div>
            </div>
            <textarea name="content" id="content-input" style="display:none">{{ old('content', $page?->content) }}</textarea>
            @error('content')
                <div class="hint" style="color:var(--c-danger)">{{ $message }}</div>
            @enderror
        </div>

    </div>
</div>
