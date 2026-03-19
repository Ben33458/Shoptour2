@extends('admin.layout')

@section('title', 'Neues Lager anlegen')

@section('actions')
    <a href="{{ route('admin.warehouses.index') }}" class="btn btn-outline btn-sm">← Zurück</a>
@endsection

@section('content')
<form method="POST" action="{{ route('admin.warehouses.store') }}">
    @csrf

    @include('admin.warehouses._form', ['warehouse' => null])

    <div style="display:flex;gap:8px;margin-top:16px">
        <button type="submit" class="btn btn-primary">Lager anlegen</button>
        <a href="{{ route('admin.warehouses.index') }}" class="btn btn-outline">Abbrechen</a>
    </div>
</form>
@endsection
