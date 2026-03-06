@extends('admin.layout')

@section('title', 'Produkt bearbeiten — ' . $product->artikelnummer)

@section('actions')
    <a href="{{ route('admin.products.show', $product) }}" class="btn btn-outline btn-sm">← Produktdetail</a>
    <a href="{{ route('admin.products.index') }}" class="btn btn-outline btn-sm">← Produktliste</a>
@endsection

@section('content')

<div class="card">
    <div class="card-header">Produktdaten bearbeiten</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.products.update', $product) }}">
            @csrf
            @method('PUT')
            @include('admin.products._form', ['product' => $product])
            <div style="margin-top:20px">
                <button type="submit" class="btn btn-primary">Änderungen speichern</button>
                <a href="{{ route('admin.products.show', $product) }}" class="btn btn-outline" style="margin-left:8px">Abbrechen</a>
            </div>
        </form>
    </div>
</div>

{{-- ─── WP-21: Product image gallery ──────────────────────────────────────── --}}
<div class="card" style="margin-top:24px">
    <div class="card-header">Produktbilder</div>
    <div class="card-body">

        {{-- Existing images --}}
        @php $images = $product->images; @endphp
        @if($images->isNotEmpty())
            <div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:20px">
                @foreach($images as $img)
                    <div style="position:relative;width:120px;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;background:#f9fafb">
                        <img src="{{ Storage::url($img->path) }}"
                             alt="{{ $img->alt_text }}"
                             style="width:100%;height:100px;object-fit:contain;padding:4px">

                        {{-- Sort controls --}}
                        <div style="display:flex;gap:2px;padding:4px;background:#f3f4f6;justify-content:center">
                            @if(!$loop->first)
                                <form method="POST" action="{{ route('admin.products.images.sort', [$product, $img]) }}" style="display:inline">
                                    @csrf
                                    <input type="hidden" name="direction" value="up">
                                    <button type="submit" class="btn btn-sm" style="padding:2px 6px;font-size:11px" title="Nach links">←</button>
                                </form>
                            @endif
                            @if(!$loop->last)
                                <form method="POST" action="{{ route('admin.products.images.sort', [$product, $img]) }}" style="display:inline">
                                    @csrf
                                    <input type="hidden" name="direction" value="down">
                                    <button type="submit" class="btn btn-sm" style="padding:2px 6px;font-size:11px" title="Nach rechts">→</button>
                                </form>
                            @endif
                        </div>

                        {{-- Delete --}}
                        <form method="POST" action="{{ route('admin.products.images.destroy', [$product, $img]) }}"
                              onsubmit="return confirm('Bild löschen?')"
                              style="position:absolute;top:4px;right:4px">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    style="background:rgba(239,68,68,0.85);border:none;color:white;border-radius:50%;width:20px;height:20px;font-size:12px;cursor:pointer;line-height:1;display:flex;align-items:center;justify-content:center"
                                    title="Löschen">✕</button>
                        </form>

                        @if($loop->first)
                            <div style="position:absolute;top:4px;left:4px;background:rgba(245,158,11,0.9);color:white;font-size:10px;padding:1px 5px;border-radius:4px">Hauptbild</div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <p style="color:#9ca3af;font-size:14px;margin-bottom:16px">Noch keine Bilder hochgeladen.</p>
        @endif

        {{-- Upload form --}}
        <form method="POST"
              action="{{ route('admin.products.images.store', $product) }}"
              enctype="multipart/form-data">
            @csrf
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp"
                       style="border:1px dashed #d1d5db;border-radius:8px;padding:8px 12px;font-size:13px;flex:1;min-width:200px">
                <button type="submit" class="btn btn-primary btn-sm">Bilder hochladen</button>
            </div>
            <p style="font-size:12px;color:#9ca3af;margin-top:6px">JPEG, PNG oder WebP, max. 5 MB pro Datei. Mehrere Dateien können auf einmal ausgewählt werden.</p>
        </form>
    </div>
</div>

@endsection
