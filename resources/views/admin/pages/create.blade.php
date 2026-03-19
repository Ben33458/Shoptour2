@extends('admin.layout')

@section('title', 'Neue Seite anlegen')

@section('actions')
    <a href="{{ route('admin.pages.index') }}" class="btn btn-sm btn-outline">← Zurück</a>
@endsection

@push('head')
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
    @include('admin.pages._editor-styles')
@endpush

@section('content')
<form method="POST" action="{{ route('admin.pages.store') }}" id="page-form">
    @csrf

    @include('admin.pages._form', ['page' => null])

    <div style="display:flex;gap:8px;margin-top:16px">
        <button type="submit" class="btn btn-primary">Seite anlegen</button>
        <a href="{{ route('admin.pages.index') }}" class="btn btn-outline">Abbrechen</a>
    </div>
</form>
@endsection

@push('scripts')
    @include('admin.pages._editor-scripts', ['content' => old('content', '')])
@endpush
