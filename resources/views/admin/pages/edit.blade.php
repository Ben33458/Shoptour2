@extends('admin.layout')

@section('title', 'Seite bearbeiten: ' . $page->title)

@section('actions')
    <a href="{{ route('admin.pages.index') }}" class="btn btn-sm btn-outline">← Zurück</a>
    <a href="{{ route('page.show', $page->slug) }}" target="_blank" class="btn btn-sm btn-outline">Vorschau</a>
@endsection

@push('head')
    <link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
    <style>
        .ql-container { font-size: 15px; }
        .ql-editor { min-height: 400px; }
        #editor-wrapper { border: 1px solid #d1d5db; border-radius: 4px; overflow: hidden; }
    </style>
@endpush

@section('content')
<form method="POST" action="{{ route('admin.pages.update', $page) }}" id="page-form">
    @csrf
    @method('PUT')

    <div class="card">
        <div class="card-header"><strong>Seite bearbeiten</strong></div>
        <div style="padding: 20px; display: flex; flex-direction: column; gap: 16px;">

            <div class="form-group">
                <label>Titel</label>
                <input type="text" name="title" value="{{ old('title', $page->title) }}"
                       class="form-control" required>
            </div>

            <div class="form-group">
                <label>URL-Pfad</label>
                <input type="text" value="/seite/{{ $page->slug }}" class="form-control"
                       disabled style="color:#888; background:#f5f5f5;">
            </div>

            <div class="form-group">
                <label>Inhalt</label>
                <div id="editor-wrapper">
                    <div id="quill-editor">{!! old('content', $page->content) !!}</div>
                </div>
                <textarea name="content" id="content-input" style="display:none;">{{ old('content', $page->content) }}</textarea>
            </div>

        </div>
        <div style="padding: 12px 20px; border-top: 1px solid #e5e7eb; display: flex; gap: 8px;">
            <button type="submit" class="btn btn-sm btn-primary">Speichern</button>
            <a href="{{ route('admin.pages.index') }}" class="btn btn-sm btn-outline">Abbrechen</a>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
    const quill = new Quill('#quill-editor', {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ heading: [1, 2, 3, false] }],
                ['bold', 'italic', 'underline'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['link'],
                ['clean'],
            ],
        },
    });

    // Vor dem Absenden: Quill-HTML in das versteckte Textarea schreiben
    document.getElementById('page-form').addEventListener('submit', function () {
        document.getElementById('content-input').value = quill.root.innerHTML;
    });
</script>
@endpush
